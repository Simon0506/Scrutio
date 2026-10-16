<?php

namespace App\Command;

use App\Entity\CaseFile;
use App\Entity\DocumentMetadata;
use App\Entity\EvidenceDocument;
use App\Entity\SolutionSchema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:case:seed-pilot', description: 'Créer le contenu provisoire de L’Héritage Valabrègue.')]
final class SeedPilotCaseCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(CaseFile::class);

        if ($repository->findOneBy(['slug' => 'heritage-valabregue']) instanceof CaseFile) {
            $io->success('L’affaire pilote existe déjà.');

            return Command::SUCCESS;
        }

        $case = new CaseFile(
            'L’Héritage Valabrègue',
            'heritage-valabregue',
            'À la mort d’Émile Valabrègue, un codicille découvert dans ses papiers modifie la répartition d’un domaine familial. Avant que la succession ne soit réglée, le cabinet doit vérifier la chronologie et la provenance des pièces.',
            'Initiation',
        );

        $deathRecord = new EvidenceDocument(
            'Extrait d’acte de décès',
            'Registre d’état civil',
            'registry',
            [
                'authority' => 'Mairie de Saint-Laurent-des-Bois',
                'registerNumber' => '2025 / 041',
                'registeredAt' => '5 mars 2025',
                'fullName' => 'Émile Valabrègue',
                'dateOfBirth' => '19 avril 1942',
                'dateOfDeath' => '4 mars 2025',
                'placeOfDeath' => 'Saint-Laurent-des-Bois',
            ],
            1,
        );

        $will = new EvidenceDocument(
            'Testament olographe',
            'Testament',
            'contract',
            [
                'office' => 'Étude Dumas & Associés',
                'reference' => 'Dossier succession EV-2017-06',
                'documentDate' => '21 juin 2017',
                'place' => 'Saint-Laurent-des-Bois',
                'clauses' => [
                    'Je lègue la maison des Tilleuls à ma fille, Mathilde Valabrègue.',
                    'Le reste de mes biens sera partagé à parts égales entre mes enfants.',
                ],
            ],
            2,
        );

        $codicil = new EvidenceDocument(
            'Codicille manuscrit',
            'Codicille testamentaire',
            'contract',
            [
                'office' => 'Étude Dumas & Associés',
                'reference' => 'Pièce jointe au dossier EV-2025-03',
                'documentDate' => '12 mars 2025',
                'place' => 'Saint-Laurent-des-Bois',
                'clauses' => [
                    'Je révoque les dispositions relatives à la maison des Tilleuls.',
                    'Je lègue désormais cette propriété à mon neveu, Adrien Valabrègue.',
                ],
            ],
            3,
        );
        $codicil->addMetadata(new DocumentMetadata('Création du fichier', '18 mars 2025 à 10:42', 1));
        $codicil->addMetadata(new DocumentMetadata('Logiciel émetteur', 'VueScan 9.7', 2));

        $coverLetter = new EvidenceDocument(
            'Courrier de transmission',
            'Courrier notarial',
            'letter',
            [
                'sender' => 'Étude Dumas & Associés',
                'recipient' => 'Cabinet d’expertise documentaire',
                'date' => '20 mars 2025',
                'subject' => 'Succession Émile Valabrègue — pièces communiquées',
                'paragraphs' => [
                    'Nous vous transmettons les documents versés au dossier de succession.',
                    'Le codicille ci-joint a été remis à notre étude après le décès de M. Valabrègue. Nous vous remercions d’en examiner la cohérence avant toute décision.',
                ],
            ],
            4,
        );

        foreach ([$deathRecord, $will, $codicil, $coverLetter] as $document) {
            $case->addDocument($document);
        }

        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'fraud_confirmed',
            'expectedAnomalies' => [[
                'code' => 'codicil_after_death',
                'type' => 'cross_document_inconsistency',
                'documentOrders' => [1, 3],
                'explanation' => 'Le codicille est daté du 12 mars 2025, huit jours après le décès établi par l’acte d’état civil.',
                'minimumSelections' => 2,
                'minimumDistinctDocuments' => 2,
                'expectedEvidence' => [
                    ['documentOrder' => 1, 'selectionKeywords' => ['4 mars 2025']],
                    ['documentOrder' => 3, 'selectionKeywords' => ['12 mars 2025']],
                ],
            ]],
            'metadataCaution' => 'La date de création du fichier correspond à une numérisation postérieure et ne prouve pas, à elle seule, une falsification.',
        ]));

        $this->entityManager->persist($case);
        $this->entityManager->flush();

        $io->success('L’affaire pilote et ses quatre pièces ont été créées.');

        return Command::SUCCESS;
    }
}
