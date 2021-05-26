# Oncoreport

## Installation

Install docker through 
https://docs.docker.com/get-docker/
Pull the pipeline image using the command

```
   docker pull grete/oncoreport:latest
```
The first thing to do after the installation of docker and the download of the pipeline is to download the cosmic file that the pipeline need. 
You will need to download both the GRCh37 version and the GRCh38 version if you want to work with both the genome version, or only one if you plan to work always with the same version.Steps:
1.	Create a folder where to put the Cosmic files
2.	Go to https://cancer.sanger.ac.uk/cosmic/download
3.	If you don’t have an account create it, otherwise log in
4.	Choose the genome version GRCh37 
5.	Download CosmicCodingMuts.vcf.gz
6.	Download CosmicResistenceMutation.tsv.gz
7.	Leave the name of the cosmic file GRCh37 as you see them above
8.	Choose the genome version GRCh38, remember when you download the file to change the name of them as I did below
9.	Download CosmicCodingMuts_hg38.vcf.gz
10. Download CosmicResistanceMutation_hg38.tsv.gz

 

 

 
 

When you download The Cosmic file for the GRCh38 version remember to change the files name
 

## Usage example
### File extension
Your fastq file need to have one of the following extension:
1.	for both the pipeline
-  .fastq.gz
-	.fastq  
-	.bam
-	.sam
-	.vcf
2. for the liquid biopsy/only tumor pipeline
-	.varianttable.txt (This one is specific from vcf produced with illumina sequencer)
The Pipeline is built to be used with three different types of data, liquid biopsy sample, a tumour only sample or a tumour-normal sample. 
When you first start the docker container you need to indicate three paths, the path where the index will be saved, the path were you have downloaded the cosmic files, and the path where you want to do your analysis and where there is your input folder, this path is called project path.


##### FOR BOTH PIPELINES
Example for linux
```
docker run -v /home/username/index:/index -v /home/username/project:/project -v /home/username/cosmic_download:/Cosmic -it grete/oncoreport:latest
```
Example for windows
```
docker run -v /home/username/index:/index -v /home/username/project:/project -v /home/username/cosmic_download:/Cosmic -it grete/oncoreport:latest
```

After you have built the container you will se your terminal change a bit, you are going to see root@ followed by a number an not anymore your username. 


At this point, you need to create the database and the index for the alignment. To do so launch:
```
bash setup_databases -db database_path -index hg19 or hg38 -ip path_index -c path_cosmic
```

Now you need to choose the right script for your sample type, if you have a liquid biopsy sample or a tumour only sample choose the pipeline called pipeline_liquid_biopsy.bash, but if you have a tumour-normal sample choose pipeline_tumVSnormal.bash pipeline. 
For both pipeline you have to set the parameters : 
- n/-name <- the name of the patient
- s/-surname <- the surname of the patient
- i/-id <- the code of the patients, or its ID, as the user prefer
- t/-tumor <-the tumour type, it has to be chosen depending on a list given by us to reassure the compatibility between the database and the information given by the user. You can find the list of tumour name at the end of this document. Remember is your string is formed by two words to put the backslash near the first one (ex. Thyroid\ Cancer)
- a/-age <- the age of the patient
- ip/-idx_path <- the path of the folder of the index 
- idx/-index <- the genome versione you want to use (hg19 or hg38)
- pp/-project_path <- the path of the project where you can find the input folder and you will create the output one
- th/-threas <- the number of threads for the sample alignment, if you are not sure write 1
- dp/-depth <- is fundamental for the depth of the analysis is the number of time that a nucleotide is read, so it’s the number of reads generated by the ngs analysis. It depends on the NGS run, it can vary between different type of analysis, panels and tools. If you don’t know what to do set it to “DP<0 “
- af/-allfreq <- set the Allele fraction filter, that is the percentage of the mutate alleles in that reads, it is helpful to understand if a mutation is probably germline or somatic, in a liquid biopsy analysis is usually set to “AF>0.3” (30%), in a solid biopsy analysis to “AF<0.4” (40%).
- fq1/-fastq1 <- the analysis can start from fastq, bam, ubam or vcf, insert this parameter with the fastq1 path if you have the fastq sample
- fq2/-fastq2 <- the second fastq sample if the analysis is a paired-end analysis
- b/-bam <- the analysis can start from fastq, bam, ubam or vcf, insert this parameter with the bam path if you have the bam sample 
- ub/-ubam <-  the analysis can start from fastq, bam, ubam or vcf, insert this parameter with the ubam path if you have the ubam sample
- pr/-paired <-  if you have inserted the ubam you need to specify if the ubam originate from a paired-end analysis or not, using yes in the former case or no in the latter 
- v/-vcf <-  the analysis can start from fastq, bam, ubam or vcf, insert this parameter with the vcf path if you have the vcf sample
- db/-database <- the path of the databases folder

Examples
```
bash pipeline_liquid_biopsy.bash -n Mario -s Rossi -g M -i AX6754R -a 45 -dp "DP<0" -af "AF>=0.4"  -t Colon\ Cancer -ip index/ -idx hg19 -th 4 -pp input -fq1 input/fastq/OGT_S2_R1_001.fastq -fq2 input/fastq/OGT_S2_R2_001.fastq -db Databases
```
```
bash  pipeline_tumVSnormal_docker.bash bash pipeline_liquid_biopsy.bash -g M -s Rossi -n Mario -i AX6754R -a 45 -t Colon\ Cancer -ip index/ -th 4 -pp input -idx hg19 -fq1 input/fastq/fastq_sample.fastq -nm1 input/normal/normal_sample.fastq -db Databases
```

### Disease
- Acoustic Neuroma
- Adenoid cystic carcinoma
- Adrenal adenoma
- Adrenal Gland Pheochromocytoma
- Adrenocortical Carcinoma
- Alveolar Rhabdomyosarcoma
- Anaplastic Large Cell Lymphoma
- Anaplastic Oligodendroglioma
- Angiosarcoma
- Any cancer type
- Astrocytoma
- B cell lymphoma
- Barrett's Adenocarcinoma
- Basal Cell Carcinoma
- Bile Duct Adenocarcinoma
- Biliary Tract Cancer
- Billiary tract
- Bladder Cancer
- Bone Cancer
- Brain Cancer
- Breast Cancer
- Bronchiolo-alveolar Adenocarcinoma
- Cervical Cancer
- Cervix Cancer
- Cholangiocarcinoma
- Chordoma
- Chuvash Polycythemia
- Colon Cancer
- Congenital Fibrosarcoma
- Dermatofibrosarcoma
- Dermatofibrosarcoma Protuberans
- Desmoid Fibromatosis
- Diffuse Intrinsic Pontine Glioma
- Diffuse Large B-cell Lymphoma
- Endometrial Cancer
- Epithelioid Hemangioendothelioma
- Epithelioid Inflammatory Myofibroblastic Sarcoma
- Erdheim-Chester histiocytosis
- Esophageal Cancer
- Essential Thrombocythemia
- Ewing Sarcoma
- Female germ cell tumor
- Female Reproductive Organ Cancer
- Fibrous histiocytoma
- Follicular Lymphoma
- Ganglioglioma
- Gastric Cancer
- Giant cell astrocytoma
- Glioblastoma
- Glioblastoma Multiforme
- Glioma
- Head And Neck Cancer
- Hematologic Cancer
- Hematologic malignancies
- Hepatic carcinoma
- Hepatocellular Cancer
- Hyper eosinophilic advanced snydrome
- Inflammatory myofibroblastic
- Inflammatory Myofibroblastic Tumor
- Intrahepatic Cholangiocarcinoma
- Langerhans-Cell Histiocytosis
- Laryngeal Cancer
- Leukemia
- Liposarcoma
- Lung Cancer
- Lymphangioleiomyomatosis
- Lymphoma
- Lynch Syndrome
- Male germ cell tumor
- Malignant astrocytoma
- Malignant Glioma
- Malignant Mesothelioma
- Malignant Peripheral Nerve Sheath Tumor
- Malignant Pleural Mesothelioma
- Malignant rhabdoid tumor
- Malignant Sertoli-Leydig Cell Tumor
- Mantle Cell Lymphoma
- Medulloblastoma
- Melanoma
- Meningioma
- Merkel Cell Carcinoma
- Mesenchymal Chondrosarcoma
- Mesothelioma
- Myeloma
- Myxoid Liposarcoma
- Neuroblastoma
- Neuroendocrine
- Neuroendocrine Tumor
- Neurofibroma
- Non-small cell lung
- NUT Midline Carcinoma
- Oligodendroglioma
- Oral Squamous Cell Carcinoma
- Oropharynx Cancer
- Osteosarcoma
- Ovarian Cancer
- Pancreatic Cancer
- Papillary Adenocarcinoma
- Paraganglioma
- Parietal Lobe Ependymoma
- Pediatric Fibrosarcoma
- Pediatric glioma
- Pericytoma
- Peripheral T-cell Lymphoma
- Peritoneal Mesothelioma
- Peutz-Jeghers Syndrome
- Pilocytic Astrocytoma
- Plexiform Neurofibroma
- Polycythemia Vera
- Prostate Cancer
- Pseudomyxoma Peritonei
- PTEN Hamartoma Tumor Syndrome
- Rectum Cancer
- Renal Cancer
- Retinoblastoma
- Rhabdoid Cancer
- Rhabdomyosarcoma
- Salivary Cancer
- Sarcoma
- Schwannoma
- Scrotum Paget's Disease
- Sezary's Disease
- Skin Squamous Cell Carcinoma
- Solid Tumor
- Stomach Cancer
- Supratentorial Glioblastoma Multiforme
- Synovial Sarcoma
- Systemic Mastocytosis
- Thymic
- Thymic Carcinoma
- Thyroid Cancer
- Tuberous Sclerosis
- Ureter Small Cell Carcinoma
- Urinary tract carcinoma
- Urothelial Carcinoma
- Uterine Cancer
- Vagina Sarcoma
- Von Hippel-Lindau Disease
- Waldenström macro globulinemia
- Waldenström's Macroglobulinemia


