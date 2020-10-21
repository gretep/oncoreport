#!/bin/bash
#hg19
PATH_COSMIC=Cosmic_downloads
PATH_INDEX=index
echo "database creation"
cd $PATH_INDEX
wget ftp://ftp.ccb.jhu.edu/pub/data/bowtie2_indexes/hg19.zip
unzip hg19.zip
wget http://hgdownload.soe.ucsc.edu/goldenPath/hg19/bigZips/hg19.fa.gz
gunzip hg19.fa.gz
cd ..
gunzip -f $PATH_COSMIC/CosmicCodingMuts.vcf.gz
gunzip -f $PATH_COSMIC/CosmicResistanceMutations.tsv.gz
cat $PATH_COSMIC/CosmicResistanceMutations.tsv >> $PATH_COSMIC/CosmicResistanceMutations.txt
rm $PATH_COSMIC/CosmicResistanceMutations.tsv
cat nightly-ClinicalEvidenceSummaries.tsv >> civic.txt
sed '1,27d' clinvar_20200327.vcf > clinvar_databasehg19.vcf
cut -f1,2,3,4,5 $PATH_COSMIC/CosmicCodingMuts.vcf > $PATH_COSMIC/CosmicCodMutDef.txt
echo "R banche"
Rscript Script_prep_banche_linea_di_comando.R
#hg38
PATH_COSMIC=Cosmic_downloads
PATH_INDEX=index
echo "database creation"
cd $PATH_INDEX
wget ftp://ftp.ncbi.nlm.nih.gov/genomes/archive/old_genbank/Eukaryotes/vertebrates_mammals/Homo_sapiens/GRCh38/seqs_for_alignment_pipelines/GCA_000001405.15_GRCh38_no_alt_analysis_set.fna.bowtie_index.tar.gz
wget http://hgdownload.soe.ucsc.edu/goldenPath/hg38/bigZips/hg38.fa.gz
gunzip hg38.fa.gz
tar xzf GCA_000001405.15_GRCh38_no_alt_analysis_set.fna.bowtie_index.tar.gz
rename 's/GCA_000001405.15_GRCh38_no_alt_analysis_set.fna.bowtie_index/hg38/g' *
cd ..
gunzip -f $PATH_COSMIC/CosmicCodingMuts_hg38.vcf.gz
gunzip -f $PATH_COSMIC/CosmicResistanceMutations_hg38.tsv.gz
cat $PATH_COSMIC/CosmicResistanceMutations_hg38.tsv >> $PATH_COSMIC/CosmicResistanceMutations_hg38.txt
rm $PATH_COSMIC/CosmicResistanceMutations_hg38.tsv
cat nightly-ClinicalEvidenceSummaries.tsv >> civic.txt
sed '1,27d' clinvar_hg38.vcf > clinvar_databasehg38.vcf
cut -f1,2,3,4,5 $PATH_COSMIC/CosmicCodingMuts_hg38.vcf > $PATH_COSMIC/CosmicCodMutDef_hg38.txt
echo "R banche"
Rscript database_hg38.R
CrossMap.py bed hg19ToHg38.over.chain.gz civic_bed.bed civic_bed_hg38.bed
Rscript civic_hg38.R
