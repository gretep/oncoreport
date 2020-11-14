<?php
/**
 * Oncoreport Web Service
 *
 * @author S. Alaimo, Ph.D. <alaimos at gmail dot com>
 */

namespace App;

use App\Exceptions\CommandException;
use App\Exceptions\IgnoredException;
use App\Exceptions\ProcessingJobException;
use Illuminate\Http\Resources\Json\JsonResource;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

final class Utils
{

    public const VERSION = '0.0.1';

    public const IGNORED_ERROR_CODE = '===IGNORED===';

    public const VALID_GENOMES = ['hg19', 'hg38'];

    public const VALID_FILTER_OPERATORS = ['lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>=',];



    /**
     * Runs a shell command and checks for successful completion of execution
     *
     * @param array         $command
     * @param string|null   $cwd
     * @param int|null      $timeout
     * @param callable|null $callback
     *
     * @return string|null
     */
    public static function runCommand(
        array $command,
        ?string $cwd = null,
        ?int $timeout = null,
        ?callable $callback = null
    ): ?string {
        $process = new Process($command, $cwd, null, null, $timeout);
        $process->run($callback);
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Map command exception to message
     *
     * @param \Symfony\Component\Process\Exception\ProcessFailedException $e
     * @param array                                                       $errorCodeMap
     *
     * @return \App\Exceptions\ProcessingJobException|\App\Exceptions\IgnoredException
     */
    public static function mapCommandException(
        ProcessFailedException $e,
        array $errorCodeMap = []
    ) {
        $code = $e->getProcess()->getExitCode();
        if (isset($errorCodeMap[$code])) {
            if ($errorCodeMap[$code] === self::IGNORED_ERROR_CODE) {
                return new IgnoredException($code, $code);
            }

            return new ProcessingJobException($errorCodeMap[$code], $code, $e);
        }

        return new ProcessingJobException($e->getMessage(), $code, $e);
    }

    /**
     * Build a zip archive from a folder
     *
     * @param string $inputFolder
     * @param string $zipArchive
     *
     * @return bool
     */
    public static function makeZipArchive(string $inputFolder, string $zipArchive): bool
    {
        $rootPath = realpath($inputFolder);
        if (!file_exists($rootPath) && !is_dir($rootPath)) {
            return false;
        }
        $zip = new ZipArchive();
        $zip->open($zipArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        return true;
    }

    /**
     * Recursively set chmod
     *
     * @param string $inputFolder
     * @param int    $mode
     *
     * @return bool
     */
    public static function recursiveChmod(string $inputFolder, int $mode): bool
    {
        $rootPath = realpath($inputFolder);
        if (!file_exists($rootPath) && !is_dir($rootPath)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath));
        foreach ($files as $name => $file) {
            @chmod($file->getRealPath(), 0777);
        }
        @chmod($rootPath, 0777);

        return true;
    }

    /**
     * Flatten a resource object removing data and links sections
     *
     * @param \Illuminate\Http\Resources\Json\JsonResource $resource
     * @param \Illuminate\Http\Request                     $request
     *
     * @return array
     */
    public static function flattenResource(JsonResource $resource, $request): array
    {
        $resArray = $resource->toArray($request);
        if (isset($resArray['data'])) {
            $tmpArray = $resArray['data'];
            if (isset($resArray['links']) && is_array($resArray['links'])) {
                foreach ($resArray['links'] as $key => $link) {
                    $tmpArray["{$key}.link"] = $link;
                }
            }

            return $tmpArray;
        }

        return $resArray;
    }

}
