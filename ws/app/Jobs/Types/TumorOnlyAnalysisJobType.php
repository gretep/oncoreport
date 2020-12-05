<?php
/**
 * Oncoreport Web Service
 *
 * @author S. Alaimo, Ph.D. <alaimos at gmail dot com>
 */

namespace App\Jobs\Types;

use App\Exceptions\ProcessingJobException;
use App\Utils;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TumorOnlyAnalysisJobType extends AbstractJob
{

    /**
     * Returns an array containing for each input parameter an help detailing its content and use.
     *
     * @return array
     */
    public static function parametersSpec(): array
    {
        return [
            'paired'               => 'A boolean value indicating whether the input if paired-end or not (OPTIONAL; default: FALSE)',
            'fastq1'               => 'The first FASTQ filename for the sample (Required if no uBAM, BAM, or VCF files are used)',
            'fastq2'               => 'The second FASTQ filename for the sample (Required if the input is paired-end and no uBAM, BAM, or VCF files are used)',
            'ubam'                 => 'The uBAM filename for the sample (Required if no FASTQ, BAM, or VCF files are used)',
            'bam'                  => 'The BAM filename for the sample (Required if no FASTQ, uBAM, or VCF files are used)',
            'vcf'                  => 'A VCF filename for a custom analysis (Required if no FASTQ, uBAM, or BAM files are used)',
            'genome'               => 'The genome version (hg19 or hg38; OPTIONAL; default: hg19)',
            'threads'              => 'The number of threads to use for the analysis (OPTIONAL; default: 1)',
            'depthFilter'          => [
                'comparison' => 'The type of comparison to be done for the sequencing depth filter (One of: lt, lte, gt, gte; OPTIONAL; default: lt)',
                'value'      => 'The value that will be used to filter the sequencing depth (OPTIONAL; default 0)',
            ],
            'alleleFractionFilter' => [
                'comparison' => 'The type of comparison to be done for the allele fraction filter (One of: lt, lte, gt, gte; OPTIONAL; default: lt)',
                'value'      => 'The value that will be used to filter the allele fraction (OPTIONAL; default 0.4)',
            ],
        ];
    }

    /**
     * Returns an array containing for each output value an help detailing its use.
     *
     * @return array
     */
    public static function outputSpec(): array
    {
        return [
            'bamOutputFile'     => 'The path and url of the BAM file produced by this analysis',
            'vcfOutputFile'     => 'The path and url of the VCF file produced by this analysis',
            'vcfPASSOutputFile' => 'The path and url of the VCF file produced by this analysis filtered to keep only PASS variants',
            'textOutputFiles'   => 'The path and url of an archive containing all text files generated by annotating the VCF file',
            'reportOutputFile'  => 'The path and url of the final report produced by this analysis',
        ];
    }

    /**
     * Handles all the computation for this job.
     * This function should throw a ProcessingJobException if something went wrong during the computation.
     * If no exceptions are thrown the job is considered as successfully completed.
     *
     * @throws \App\Exceptions\ProcessingJobException
     * @throws \Throwable
     */
    public function handle(): void
    {
        try {
            $this->log('Starting analysis.');
            $patient = $this->model->patient;
            throw_unless($patient, new ProcessingJobException('This job is not tied to any patient. Unable to run the analysis.'));
            $paired = (bool)$this->model->getParameter('paired', false);
            $fastq1 = $this->model->getParameter('fastq1');
            $fastq2 = $this->model->getParameter('fastq2');
            $ubam = $this->model->getParameter('ubam');
            $bam = $this->model->getParameter('bam');
            $vcf = $this->model->getParameter('vcf');
            $genome = $this->model->getParameter('genome', Utils::VALID_GENOMES[0]);
            $threads = $this->model->getParameter('threads', 1);
            $depthFilterOperator = Utils::VALID_FILTER_OPERATORS[$this->model->getParameter('depthFilter.comparison', 'lt')];
            $depthFilterValue = (double)$this->model->getParameter('depthFilter.value', 0);
            $alleleFractionFilterOperator = Utils::VALID_FILTER_OPERATORS[$this->model->getParameter(
                'alleleFractionFilter.comparison',
                'lt'
            )];
            $alleleFractionFilterValue = (double)$this->model->getParameter('alleleFractionFilter.value', 0.4);
            [$outputRelative, $outputAbsolute,] = $this->getJobFilePaths('output_');
            throw_if(
                !file_exists($outputAbsolute) && !mkdir($outputAbsolute, 0777, true) && !is_dir($outputAbsolute),
                ProcessingJobException::class,
                sprintf('Directory "%s" was not created', $outputAbsolute)
            );
            $depthFilter = sprintf("DP%s%.2f", $depthFilterOperator, $depthFilterValue);
            $alleleFractionFilter = sprintf("AF%s%.2f", $alleleFractionFilterOperator, $alleleFractionFilterValue);
            $command = [
                'bash',
                self::scriptPath('pipeline_tumVSnormal.bash'),
                '-i',
                $patient->code,
                '-s',
                $patient->last_name,
                '-n',
                $patient->first_name,
                '-a',
                $patient->age,
                '-g',
                $patient->gender,
                '-t',
                $patient->disease->name,
                '-pp',
                $outputAbsolute,
                '-th',
                $threads,
                '-gn',
                $genome,
                '-dp',
                $depthFilter,
                '-af',
                $alleleFractionFilter,
            ];
            if ($this->fileExists($vcf)) {
                $command = [...$command, '-v', $vcf];
            } elseif ($this->fileExists($bam)) {
                $command = [
                    ...$command,
                    '-b',
                    $bam,
                ];
            } elseif ($this->fileExists($ubam)) {
                $command = [
                    ...$command,
                    '-ub',
                    $ubam,
                    '-pr',
                    $paired ? 'yes' : 'no',
                ];
            } elseif ($this->fileExists($fastq1)) {
                $command = [
                    ...$command,
                    '-fq1',
                    $fastq1,
                ];
                if ($paired && $this->fileExists($fastq2)) {
                    $command = [
                        ...$command,
                        '-fq2',
                        $fastq2,
                    ];
                } else {
                    throw new ProcessingJobException('Unable to validate second fastq files with a paired-end analysis.');
                }
            } else {
                throw new ProcessingJobException('No valid input files have been specified.');
            }
            $model = $this->model;
            self::runCommand(
                $command,
                $this->getAbsoluteJobDirectory(),
                null,
                static function ($type, $buffer) use ($model) {
                    $model->appendLog($buffer, false);
                },
                [
                    1   => 'An invalid parameter has been detected',
                    100 => 'Unable to convert uBAM to FASTQ',
                    101 => 'Unable to trim FASTQ file',
                    102 => 'Unable to align FASTQ file',
                    103 => 'Unable to add read groups to BAM file',
                    104 => 'Unable to sort BAM file',
                    105 => 'Unable to reorder BAM file',
                    106 => 'Unable to call variants',
                    107 => 'Unable to filter variants',
                    108 => 'Unable to select PASS variants',
                    109 => 'Unable to filter by Depth',
                    110 => 'Unable to filter by Depth',
                    111 => 'Unable to process Illumina VariantTable Format',
                    112 => 'Unable to split INDELs and SNPs',
                    113 => 'Unable to filter SNPs by Allele Frequency',
                    114 => 'Unable to merge filtered SNPs with INDELs',
                    115 => 'Unable to select PASS variants',
                    116 => 'Unable to extract Germline variants from VCF',
                    117 => 'Unable to extract Somatic variants from VCF',
                    118 => 'Unable to prepare variants file for annotation',
                    119 => 'Unable to prepare input file for annotation',
                    120 => 'Unable to build report output',
                    121 => 'Unable to clean unused folders',
                ]
            );
            throw_unless(
                $this->fileExistsRelative($outputRelative . '/txt'),
                ProcessingJobException::class,
                'Unable to generate report intermediate files.'
            );
            throw_unless(
                $this->fileExistsRelative($outputRelative . '/output/report.html'),
                ProcessingJobException::class,
                'Unable to generate report output file.'
            );
            $this->log('Building intermediate archive');
            Utils::makeZipArchive(
                $this->absoluteJobPath($outputRelative . '/txt'),
                $this->absoluteJobPath($outputRelative . '/output/intermediate.zip')
            );
            $this->log('Writing output');
            $this->setOutput(
                [
                    'type'              => Utils::TUMOR_ONLY_TYPE,
                    'bamOutputFile'     => $this->getFilePathsForOutput($outputRelative . '/bam_ordered/ordered.bam'),
                    'vcfOutputFile'     => $this->getFilePathsForOutput($outputRelative . '/mutect/variants.vcf'),
                    'vcfPASSOutputFile' => $this->getFilePathsForOutput($outputRelative . '/pass_final/variants.vcf'),
                    'textOutputFiles'   => $this->getFilePathsForOutput($outputRelative . '/output/intermediate.zip'),
                    'reportOutputFile'  => $this->getFilePathsForOutput($outputRelative . '/output/report.html'),
                ]
            );
            $this->log('Analysis completed.');
        } catch (Exception $e) {
            throw_if($e instanceof ProcessingJobException, $e);
            throw new ProcessingJobException('An error occurred during job processing.', 0, $e);
        }
    }

    /**
     * Returns a description for this job
     *
     * @return string
     */
    public static function description(): string
    {
        return 'Runs the tumor-only analysis';
    }

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Tumor Only';
    }

    /**
     * @inheritDoc
     */
    public static function validationSpec(Request $request): array
    {
        $parameters = (array)$request->get('parameters', []);

        return [
            'paired'                          => ['filled', 'boolean'],
            'fastq1'                          => ['nullable', 'required_without_all:ubam,bam,vcf'],
            'fastq2'                          => [
                'nullable',
                Rule::requiredIf(
                    static function () use ($parameters) {
                        $fastq = data_get($parameters, 'fastq1');

                        return ((bool)($parameters['paired'] ?? false)) && !empty($fastq);
                    }
                ),
            ],
            'ubam'                            => ['nullable', 'required_without_all:fastq1,bam,vcf'],
            'bam'                             => ['nullable', 'required_without_all:fastq1,ubam,vcf'],
            'vcf'                             => ['nullable', 'required_without_all:fastq1,bam,ubam'],
            'genome'                          => ['filled', Rule::in(Utils::VALID_GENOMES)],
            'threads'                         => ['filled', 'integer'],
            'depthFilter'                     => ['filled', 'array'],
            'depthFilter.comparison'          => ['filled', Rule::in(array_keys(Utils::VALID_FILTER_OPERATORS))],
            'depthFilter.value'               => ['filled', 'numeric'],
            'alleleFractionFilter'            => ['filled', 'array'],
            'alleleFractionFilter.comparison' => ['filled', Rule::in(array_keys(Utils::VALID_FILTER_OPERATORS))],
            'alleleFractionFilter.value'      => ['filled', 'numeric'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function isInputValid(): bool
    {
        if (!in_array($this->model->getParameter('genome', Utils::VALID_GENOMES[0]), Utils::VALID_GENOMES, true)) {
            return false;
        }
        if ($this->model->getParameter('threads', 1) <= 0) {
            return false;
        }
        $paired = (bool)$this->model->getParameter('paired', false);
        if ($this->validateFileParameter('vcf')) {
            return true;
        }
        if ($this->validateFileParameter('bam')) {
            return true;
        }
        if ($this->validateFileParameter('ubam')) {
            return true;
        }
        if ($this->validateFileParameter('fastq1')) {
            if (!$paired) {
                return true;
            }
            if ($this->validateFileParameter('fastq2')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public static function patientInputState(): string
    {
        return self::PATIENT_REQUIRED;
    }
}
