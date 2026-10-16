<?php

namespace App\Command;

use App\Entity\CaseFile;
use App\Entity\ContentPack;
use App\Entity\DocumentMetadata;
use App\Entity\EvidenceDocument;
use App\Entity\SolutionSchema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:case:seed-second', description: 'Créer Le Port silencieux et son pack Les Dossiers d’atelier.')]
final class SeedSecondCaseCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $caseRepository = $this->entityManager->getRepository(CaseFile::class);

        if ($caseRepository->findOneBy(['slug' => 'le-port-silencieux']) instanceof CaseFile) {
            $io->success('Le Port silencieux existe déjà.');

            return Command::SUCCESS;
        }

        $packRepository = $this->entityManager->getRepository(ContentPack::class);
        $pack = $packRepository->findOneBy(['slug' => 'les-dossiers-datelier']);
        if (!$pack instanceof ContentPack) {
            $pack = new ContentPack(
                'Les Dossiers d’atelier',
                'les-dossiers-datelier',
                'Des affaires où certificats, provenances et pièces matérielles doivent être confrontés.',
                priceAmountInCents: 499,
                displayOrder: 2,
            );
        }

        $case = new CaseFile(
            'Le Port silencieux',
            'le-port-silencieux',
            'Après la disparition d’une toile assurée pour une somme importante, un collectionneur réclame une indemnisation. Avant de conclure, le cabinet doit vérifier l’attribution de l’œuvre, sa provenance et la cohérence de son dossier matériel.',
            'Intermédiaire',
            displayOrder: 1,
            pack: $pack,
        );

        $deathRecord = new EvidenceDocument(
            'Extrait du registre des décès',
            'État civil',
            'registry',
            [
                'authority' => 'Archives municipales d’Arvieux',
                'registerNumber' => '1917 / 208',
                'fullName' => 'Auguste Varenne',
                'dateOfBirth' => '2 février 1857',
                'dateOfDeath' => '18 septembre 1917',
                'placeOfDeath' => 'Arvieux',
                'registeredAt' => '19 septembre 1917',
            ],
            1,
        );

        $certificate = new EvidenceDocument(
            'Certificat d’authenticité',
            'Certificat d’expertise',
            'contract',
            [
                'office' => 'Cabinet Ravel, expertise des œuvres',
                'reference' => 'Certificat CERT-AV-27',
                'documentDate' => '4 juin 2024',
                'place' => 'Paris',
                'clauses' => [
                    'Le tableau Le Port silencieux, inventaire AV-27, est attribué au peintre Auguste Varenne.',
                    'Le paraphe « A. Varenne, 1923 » a été porté sur la toile par l’artiste lui-même.',
                    'Cette signature date l’exécution du tableau de 1923.',
                ],
            ],
            2,
        );
        $certificate->addMetadata(new DocumentMetadata('Création du fichier', '10 octobre 2025 à 09:14', 1));
        $certificate->addMetadata(new DocumentMetadata('Logiciel émetteur', 'Epson Scan 2', 2));

        $galleryRegistration = new EvidenceDocument(
            'Extrait d’immatriculation de la Galerie des Quais',
            'Registre du commerce',
            'contract',
            [
                'office' => 'Greffe du tribunal de commerce d’Arvieux',
                'reference' => 'RCS A-26-05-02',
                'documentDate' => '2 mai 1926',
                'place' => 'Arvieux',
                'clauses' => [
                    'La Galerie des Quais est immatriculée et débute son activité à cette date.',
                    'Son activité déclarée est le commerce et l’expertise de tableaux.',
                ],
            ],
            3,
        );

        $provenanceLetter = new EvidenceDocument(
            'Attestation de provenance',
            'Courrier de galerie',
            'letter',
            [
                'sender' => 'Galerie des Quais',
                'recipient' => 'Cabinet Ravel, expertise des œuvres',
                'date' => '28 mai 2024',
                'subject' => 'Historique de la toile AV-27',
                'paragraphs' => [
                    'La toile Le Port silencieux aurait été vendue par notre galerie à Lucien Béraud le 16 septembre 1921.',
                    'L’œuvre serait ensuite restée dans la collection familiale jusqu’à son acquisition par le déclarant.',
                ],
            ],
            4,
        );

        $restorationReport = new EvidenceDocument(
            'Rapport de constat matériel',
            'Rapport de restauration',
            'contract',
            [
                'office' => 'Atelier Perrin, conservation-restauration',
                'reference' => 'Rapport RV-24-0912 · inventaire AV-27',
                'documentDate' => '12 septembre 2024',
                'place' => 'Lyon',
                'clauses' => [
                    'La toile examinée porte au revers l’étiquette d’inventaire AV-27.',
                    'Dimensions de la toile hors cadre : 61 x 46 cm.',
                    'La mesure a été prise sur le support peint, sans le cadre.',
                ],
            ],
            5,
        );

        $insuranceLetter = new EvidenceDocument(
            'Annexe au dossier de sinistre',
            'Courrier d’assurance',
            'letter',
            [
                'sender' => 'Cabinet Nord-Risques',
                'recipient' => 'Compagnie Aurore, service sinistres',
                'date' => '14 octobre 2025',
                'subject' => 'Dossier NR-25-104 · Le Port silencieux',
                'paragraphs' => [
                    'Le sinistre a été déclaré le 8 octobre 2025 après la disparition de la toile.',
                    'Objet déclaré : Le Port silencieux, Auguste Varenne, inventaire AV-27.',
                    'Dimensions de la toile hors cadre au contrat : 76 x 61 cm.',
                ],
            ],
            6,
        );

        foreach ([$deathRecord, $certificate, $galleryRegistration, $provenanceLetter, $restorationReport, $insuranceLetter] as $document) {
            $case->addDocument($document);
        }

        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'fraud_confirmed',
            'expectedAnomalies' => [
                [
                    'code' => 'signature_after_death',
                    'type' => 'anachronism',
                    'documentOrders' => [2],
                    'explanation' => 'Le certificat attribue à Auguste Varenne une signature datée de 1923, alors que le registre établit son décès en 1917.',
                    'minimumSelections' => 1,
                    'expectedEvidence' => [
                        ['documentOrder' => 2, 'selectionKeywords' => ['A. Varenne, 1923']],
                    ],
                    'acceptedVariants' => [[
                        'type' => 'cross_document_inconsistency',
                        'minimumSelections' => 2,
                        'minimumDistinctDocuments' => 2,
                        'expectedEvidence' => [
                            ['documentOrder' => 1, 'selectionKeywords' => ['18 septembre 1917']],
                            ['documentOrder' => 2, 'selectionKeywords' => ['A. Varenne, 1923']],
                        ],
                    ]],
                ],
                [
                    'code' => 'gallery_before_registration',
                    'type' => 'cross_document_inconsistency',
                    'documentOrders' => [3, 4],
                    'explanation' => 'L’attestation situe une vente en 1921 par une galerie qui n’a été immatriculée qu’en 1926.',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 3, 'selectionKeywords' => ['2 mai 1926']],
                        ['documentOrder' => 4, 'selectionKeywords' => ['16 septembre 1921']],
                    ],
                ],
                [
                    'code' => 'inconsistent_canvas_dimensions',
                    'type' => 'cross_document_inconsistency',
                    'documentOrders' => [5, 6],
                    'explanation' => 'Les deux pièces décrivent la toile AV-27 hors cadre, mais indiquent respectivement 61 x 46 cm et 76 x 61 cm.',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 5, 'selectionKeywords' => ['61 x 46 cm']],
                        ['documentOrder' => 6, 'selectionKeywords' => ['76 x 61 cm']],
                    ],
                ],
            ],
            'metadataCaution' => 'La date du 10 octobre 2025 concerne la numérisation du certificat, pas sa rédaction. Elle est postérieure au signalement du sinistre, mais ne prouve pas à elle seule une falsification.',
        ]));

        $this->entityManager->persist($pack);
        $this->entityManager->persist($case);
        $this->entityManager->flush();

        $io->success('Le Port silencieux et le pack Les Dossiers d’atelier ont été créés avec six pièces et trois anomalies attendues.');

        return Command::SUCCESS;
    }
}
