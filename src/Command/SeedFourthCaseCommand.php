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

#[AsCommand(name: 'app:case:seed-fourth', description: 'Créer l’affaire gratuite Pro Le Contrôle fantôme.')]
final class SeedFourthCaseCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pack = $this->entityManager->getRepository(ContentPack::class)->findOneBy(['slug' => 'les-dossiers-datelier']);
        if (!$pack instanceof ContentPack) {
            $pack = new ContentPack(
                'Les Dossiers d’atelier',
                'les-dossiers-datelier',
                'Des affaires où certificats, provenances et pièces matérielles doivent être confrontés.',
                priceAmountInCents: 499,
                displayOrder: 2,
            );
            $this->entityManager->persist($pack);
        }

        $case = $this->entityManager->getRepository(CaseFile::class)->findOneBy(['slug' => 'le-controle-fantome']);
        if ($case instanceof CaseFile) {
            $case->setPack($pack);
            $this->entityManager->flush();
            $io->success('Le Contrôle fantôme est bien rattaché au pack Les Dossiers d’atelier.');

            return Command::SUCCESS;
        }

        $case = new CaseFile(
            'Le Contrôle fantôme',
            'le-controle-fantome',
            'À la clinique des Aulnes, le règlement d’une intervention biomédicale est contesté. Le dossier du prestataire et les exports des services internes ne racontent pas exactement la même histoire. Le cabinet doit apprécier la fiabilité du rapport, la réalité de l’intervention et les justificatifs financiers, sans se prononcer sur un diagnostic médical.',
            'Pro',
            displayOrder: 4,
            pack: $pack,
        );

        $mandate = new EvidenceDocument('Mandat de contrôle documentaire', 'Mandat', 'letter', [
            'sender' => 'Clinique des Aulnes / Direction administrative',
            'recipient' => 'Cabinet d’expertise documentaire',
            'date' => '4 juin 2025',
            'subject' => 'Dossier de maintenance BM-25-0512',
            'paragraphs' => [
                'La société Servimed a transmis un rapport de maintenance et une facture concernant le moniteur de surveillance MA-204. La direction demande leur examen avant clôture du litige de règlement.',
                'Les pièces internes sont fournies sous forme d’extraits certifiés par leurs services émetteurs. Les horaires sont tous exprimés en heure locale de Paris ; les identifiants des agents sont uniques.',
                'Le périmètre porte sur les documents, les mouvements de matériel et le paiement. Aucun dossier de patient n’est communiqué.',
            ],
        ], 1);

        $inventory = new EvidenceDocument('Fiche du parc biomédical', 'Inventaire biomédical', 'contract', [
            'office' => 'Clinique des Aulnes / Service biomédical',
            'reference' => 'Parc MA-204 / série MS-204-778',
            'documentDate' => '30 avril 2025',
            'place' => 'Orléans',
            'clauses' => [
                'Moniteur MA-204, numéro de série MS-204-778, affecté au local technique du service de surveillance depuis le 7 avril 2025.',
                'Contrôle programmé le 12 mai 2025. Prestataire : Servimed ; agent désigné : Camille Roussel, matricule SR-18.',
                'Une remise en service exige un compte rendu signé et l’identification du lot de toute pièce remplacée. Aucun changement de numéro d’inventaire n’est prévu.',
            ],
        ], 2);

        $release = new EvidenceDocument('Bulletin de mise à disposition du logiciel', 'Bulletin technique', 'contract', [
            'office' => 'Medalis Instruments / Support technique',
            'reference' => 'Bulletin DIAG-7 / distribution France',
            'documentDate' => '20 mai 2025',
            'place' => 'Lyon',
            'clauses' => [
                'Le logiciel de diagnostic Medalis Diag 7.0 est mis à disposition des prestataires le 20 mai 2025 à 08:00.',
                'Aucune version préliminaire, licence de démonstration ou distribution anticipée de Diag 7.0 n’a été fournie. Jusqu’à cette date, seule Diag 6.4 était disponible.',
                'Ce bulletin concerne la console du technicien, non le logiciel embarqué dans le moniteur.',
            ],
        ], 3);

        $report = new EvidenceDocument('Rapport d’intervention Servimed', 'Rapport de maintenance', 'contract', [
            'office' => 'Servimed / Maintenance biomédicale',
            'reference' => 'Rapport BM-25-0512 / MA-204',
            'documentDate' => '12 mai 2025',
            'place' => 'Clinique des Aulnes, Orléans',
            'clauses' => [
                'Intervention sur le moniteur MS-204-778 le 12 mai 2025, de 09:00 à 11:00, réalisée sur place par Camille Roussel, matricule SR-18.',
                'Diagnostic exécuté le 12 mai 2025 avec Medalis Diag 7.0, installé sur la console du technicien.',
                'Module de mesure remplacé le 12 mai 2025 à 10:15 ; pièce prélevée sur le lot L-250516, fourni par Médilog.',
                'Moniteur remis au service biomédical à 11:00. Cette pièce est présentée comme la copie numérique strictement identique du PDF scellé au registre de dépôt ARCH-BM-513.',
            ],
        ], 4);
        $report->addMetadata(new DocumentMetadata('Format du fichier', 'PDF natif exporté de la console', 1));
        $report->addMetadata(new DocumentMetadata('Empreinte SHA-256 du fichier fourni', str_repeat('b7', 32), 2));
        $report->addMetadata(new DocumentMetadata('Création du fichier', '12 mai 2025 à 11:08', 3));

        $attendance = new EvidenceDocument('Journal de présence du technicien', 'Journal de présence', 'contract', [
            'office' => 'Centre médical des Rives / Accueil technique',
            'reference' => 'Extrait certifié JR-250512 / agent SR-18',
            'documentDate' => '13 mai 2025',
            'place' => 'Blois',
            'clauses' => [
                'Camille Roussel, SR-18 : présence physique au Centre médical des Rives à Blois le 12 mai 2025, de 08:45 à 12:30.',
                'L’agent a été accompagné en continu par le responsable technique durant cette plage, sans sortie intermédiaire ni intervention à distance.',
                'L’extrait porte sur la même journée et le même matricule que le rapport BM-25-0512 ; il ne s’agit pas d’une réservation prévisionnelle.',
            ],
        ], 5);

        $delivery = new EvidenceDocument('Attestation de traçabilité du lot', 'Traçabilité des pièces', 'contract', [
            'office' => 'Médilog / Qualité logistique',
            'reference' => 'Lot L-250516 / bordereau ML-1615',
            'documentDate' => '19 mai 2025',
            'place' => 'Tours',
            'clauses' => [
                'Le lot L-250516 a été fabriqué, libéré et livré pour la première fois à Servimed le 16 mai 2025 à 15:30.',
                'Aucun exemplaire de ce lot n’a été expédié, prêté ou mis en stock avant le 16 mai 2025. L’identifiant L-250516 ne désigne aucun lot antérieur.',
                'Le bordereau ML-1615 est le seul mouvement d’entrée de ce lot dans le stock Servimed.',
            ],
        ], 6);

        $archive = new EvidenceDocument('Registre de dépôt scellé', 'Registre numérique', 'contract', [
            'office' => 'Clinique des Aulnes / Archives techniques',
            'reference' => 'Registre ARCH-BM-513',
            'documentDate' => '13 mai 2025',
            'place' => 'Orléans',
            'clauses' => [
                'Dépôt du rapport BM-25-0512 reçu le 13 mai 2025 à 08:40. Empreinte SHA-256 du PDF original scellé : ' . str_repeat('a3', 32) . '.',
                'Le dépôt est conservé en écriture seule et n’a fait l’objet d’aucun remplacement. La copie numérique communiquée pour cet audit est annoncée sans conversion, réexport ni numérisation.',
                'L’empreinte recalculée du fichier fourni figure dans ses métadonnées. Une copie strictement identique doit conserver la même empreinte que le PDF scellé.',
            ],
        ], 7);

        $invoice = new EvidenceDocument('Facture de maintenance', 'Facture', 'contract', [
            'office' => 'Servimed / Facturation',
            'reference' => 'Facture SV-2025-148 / BM-25-0512',
            'documentDate' => '21 mai 2025',
            'place' => 'Orléans',
            'clauses' => [
                'Facturation forfaitaire de l’intervention sur MA-204. L’extrait communiqué porte sur l’identification de la prestation et le total TTC.',
                'Le règlement est à effectuer sur le compte habituel de Servimed, avec la référence SV-2025-148.',
            ],
            'visualExcerpt' => [
                'caption' => 'Zone du total sur la copie de facture',
                'label' => 'Total TTC',
                'underprint' => '480,00 EUR',
                'overprint' => '4 800,00 EUR',
            ],
        ], 8);
        $invoice->addMetadata(new DocumentMetadata('Nature du fichier', 'Copie couleur de la facture papier', 1));

        $payment = new EvidenceDocument('Avis de règlement', 'Avis bancaire', 'contract', [
            'office' => 'Clinique des Aulnes / Trésorerie',
            'reference' => 'Règlement RG-250528 / SV-2025-148',
            'documentDate' => '28 mai 2025',
            'place' => 'Orléans',
            'clauses' => [
                'Virement exécuté le 28 mai 2025 : 4 800,00 EUR au bénéficiaire Servimed, référence SV-2025-148.',
                'Ce règlement a été déclenché sur la base du total visible sur la facture transmise au service comptable.',
                'Le compte bénéficiaire correspond au compte contractuel habituel. Aucun changement de coordonnées bancaires n’est intervenu.',
            ],
        ], 9);

        $transfer = new EvidenceDocument('Bordereau de communication du dossier', 'Courrier', 'letter', [
            'sender' => 'Clinique des Aulnes / Archives techniques',
            'recipient' => 'Cabinet d’expertise documentaire',
            'date' => '4 juin 2025',
            'subject' => 'Communication des pièces BM-25-0512',
            'paragraphs' => [
                'Les pièces papier ont été numérisées en couleur le 3 juin 2025. Cette date de fichier ne modifie pas leur date d’émission. Le rapport Servimed fait exception : il est fourni en PDF natif, sans conversion, conformément au registre ARCH-BM-513.',
                'Les documents ne permettent pas, à eux seuls, de conclure à l’état de santé d’un patient ou à la sécurité clinique du moniteur. L’audit demandé est exclusivement documentaire.',
            ],
        ], 10);

        foreach ([$mandate, $inventory, $release, $report, $attendance, $delivery, $archive, $invoice, $payment, $transfer] as $document) {
            if ($document !== $report && $document !== $invoice) {
                $document->addMetadata(new DocumentMetadata('Création de la copie numérique', '3 juin 2025 à 09:00', 1));
            }
            $case->addDocument($document);
        }

        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'fraud_confirmed',
            'expectedAnomalies' => [
                [
                    'code' => 'software_before_release',
                    'type' => 'anachronism',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 3, 'selectionKeywords' => ['20 mai 2025 à 08:00']],
                        ['documentOrder' => 4, 'selectionKeywords' => ['Diagnostic exécuté le 12 mai 2025 avec Medalis Diag 7.0']],
                    ],
                    'acceptedVariants' => [['type' => 'cross_document_inconsistency']],
                    'explanation' => 'Le rapport revendique Diag 7.0 le 12 mai, alors que sa première distribution sans accès anticipé date du 20 mai. Le contrôle du 12 mai ne pouvait pas utiliser cette version.',
                ],
                [
                    'code' => 'technician_elsewhere',
                    'type' => 'cross_document_inconsistency',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 4, 'selectionKeywords' => ['de 09:00 à 11:00']],
                        ['documentOrder' => 5, 'selectionKeywords' => ['de 08:45 à 12:30']],
                    ],
                    'explanation' => 'SR-18 est annoncé sur place à Orléans de 09:00 à 11:00, mais sa présence continue à Blois de 08:45 à 12:30 exclut cette intervention physique.',
                ],
                [
                    'code' => 'part_before_manufacture',
                    'type' => 'cross_document_inconsistency',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 4, 'selectionKeywords' => ['Module de mesure remplacé le 12 mai 2025']],
                        ['documentOrder' => 6, 'selectionKeywords' => ['16 mai 2025 à 15:30']],
                    ],
                    'acceptedVariants' => [['type' => 'anachronism']],
                    'explanation' => 'Le module aurait été posé le 12 mai, quatre jours avant la fabrication et la première livraison du lot L-250516, sans stock ni exemplaire antérieur.',
                ],
                [
                    'code' => 'invoice_total_overprint',
                    'type' => 'graphic_forgery',
                    'expectedEvidence' => [
                        ['documentOrder' => 8, 'selectionKeywords' => ['4 800,00 EUR']],
                    ],
                    'explanation' => 'Dans la zone du total, un montant de 4 800,00 EUR est superposé sur une plage de papier claire, décalée par rapport au montant sous-jacent de 480,00 EUR dont des traces restent visibles. Le règlement reprend le montant majoré.',
                ],
                [
                    'code' => 'report_hash_mismatch',
                    'type' => 'metadata_inconsistency',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 4, 'selectionKeywords' => [str_repeat('b7', 32)]],
                        ['documentOrder' => 7, 'selectionKeywords' => [str_repeat('a3', 32)]],
                    ],
                    'acceptedVariants' => [['type' => 'cross_document_inconsistency']],
                    'explanation' => 'Le PDF fourni porte une empreinte différente du PDF scellé, bien que sa transmission soit déclarée strictement identique, sans conversion. Il ne s’agit pas du même fichier binaire ; cet écart se distingue d’une simple date de scan tardive.',
                ],
            ],
            'metadataCaution' => 'La numérisation en juin des pièces papier est expliquée par le bordereau et ne constitue pas une anomalie. L’incohérence technique porte sur l’empreinte du rapport natif prétendument identique, pas sur la date des scans.',
        ]));

        $this->entityManager->persist($pack);
        $this->entityManager->persist($case);
        $this->entityManager->flush();
        $io->success('Le Contrôle fantôme a été rattaché au pack Les Dossiers d’atelier.');

        return Command::SUCCESS;
    }
}
