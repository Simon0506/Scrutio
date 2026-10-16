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

#[AsCommand(name: 'app:case:seed-fifth', description: 'Créer l’affaire gratuite Pro Le Contrat du Moulin.')]
final class SeedFifthCaseCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->entityManager->getRepository(CaseFile::class)->findOneBy(['slug' => 'le-contrat-du-moulin']) instanceof CaseFile) {
            $io->success('Le Contrat du Moulin existe déjà.');

            return Command::SUCCESS;
        }

        $case = new CaseFile(
            'Le Contrat du Moulin',
            'le-contrat-du-moulin',
            'Un héritier réclame le remboursement de 45 000 EUR que son père aurait prêtés à un ancien associé pour rénover un moulin industriel. L’associé affirme qu’il s’agissait d’un apport au compte courant. Une reconnaissance de dette existe, mais les pièces comptables divergent et un avenant rectificatif évoqué par courriel manque au dossier. Le cabinet doit décider si les pièces suffisent à établir la nature et l’exigibilité de la créance.',
            'Pro',
            displayOrder: 5,
        );

        $acknowledgement = new EvidenceDocument('Reconnaissance de dette manuscrite', 'Acte manuscrit', 'contract', [
            'office' => 'Document privé versé par le demandeur',
            'reference' => 'Pièce P-01 / original non communiqué',
            'documentDate' => '14 juin 2010',
            'place' => 'Moulin de la Sèvre',
            'clauses' => [
                'Je soussigné Paul Dervaux reconnais devoir à André Vignal la somme de 45 000 EUR remise pour les travaux du moulin.',
                'Cette somme sera restituée après la fin des travaux, selon les modalités convenues entre nous.',
                'Signature attribuée à Paul Dervaux. La copie transmise ne permet pas d’examiner le papier original ni son verso.',
            ],
        ], 1);

        $bankStatement = new EvidenceDocument('Relevé bancaire du défunt', 'Relevé bancaire', 'contract', [
            'office' => 'Banque du Centre / compte d’André Vignal',
            'reference' => 'Extrait certifié du 18 juin 2010 / P-02',
            'documentDate' => '30 juin 2010',
            'place' => 'Niort',
            'clauses' => [
                '18 juin 2010 : virement de 45 000,00 EUR à la société Moulin de la Sèvre SARL.',
                'Libellé saisi lors de l’ordre : « Apport compte courant d’associé ».',
                'Le virement a été exécuté. Le relevé établit le mouvement des fonds, mais ne précise pas à lui seul sa qualification juridique.',
            ],
        ], 2);

        $statutes = new EvidenceDocument('Statuts enregistrés de la société', 'Statuts de société', 'contract', [
            'office' => 'Greffe du tribunal de commerce de Niort',
            'reference' => 'Moulin de la Sèvre SARL / dépôt du 3 mars 2008 / P-03',
            'documentDate' => '3 mars 2008',
            'place' => 'Niort',
            'clauses' => [
                'Associés lors de la constitution : Paul Dervaux et André Vignal, à parts égales.',
                'Les apports au capital sont fixés à 12 000 EUR chacun. Toute modification de la répartition du capital doit faire l’objet d’un acte enregistré.',
                'Les signatures figurant au dépôt sont attribuées aux deux associés. Le document date de quinze ans avant la reconnaissance de dette.',
            ],
        ], 3);

        $correspondence = new EvidenceDocument('Échange de courriels entre associés', 'Correspondance', 'letter', [
            'sender' => 'Paul Dervaux et André Vignal',
            'recipient' => 'Échange privé versé par le demandeur',
            'date' => '7 septembre 2021',
            'subject' => 'Régularisation du financement du moulin / P-04',
            'paragraphs' => [
                'Paul Dervaux écrit : « Il faut régulariser les 45 000 du chantier. L’avenant rectificatif signé en 2021 doit remplacer notre premier écrit. »',
                'André Vignal répond : « Je me souviens de nos échanges, mais je ne retrouve pas cet avenant dans les archives que j’ai conservées. »',
                'Aucune pièce jointe n’accompagne les courriels transmis. Ils évoquent un avenant, sans en établir le contenu ni confirmer qu’il a été signé par les deux parties.',
            ],
        ], 4);

        $ledger = new EvidenceDocument('Extrait du grand livre comptable', 'Grand livre', 'contract', [
            'office' => 'Moulin de la Sèvre SARL / tenue comptable',
            'reference' => 'Compte associé 455 / exercice 2010 / P-05',
            'documentDate' => '31 décembre 2010',
            'place' => 'Niort',
            'clauses' => [
                '18 juin 2010 : crédit de 45 000,00 EUR au compte « Prêt à terme fixé — André Vignal ».',
                'La ligne porte la référence de rapprochement VIR-180610, correspondant à la date du virement bancaire.',
                'L’extrait ne contient ni l’écriture de contrepassation ni une pièce justificative décrivant les modalités de remboursement.',
            ],
        ], 5);

        $graphology = new EvidenceDocument('Rapport d’expertise graphologique préalable', 'Rapport d’expertise', 'contract', [
            'office' => 'Cabinet Écriture & Traces',
            'reference' => 'Rapport GE-2024-118 / P-06',
            'documentDate' => '12 février 2024',
            'place' => 'Poitiers',
            'clauses' => [
                'Comparaison réalisée sur les reproductions de P-01 et P-03 communiquées par le demandeur ; aucun examen matériel des originaux n’a été effectué.',
                'Les différences de tracé et d’inclinaison observées restent compatibles avec une évolution naturelle de la signature sur quinze ans.',
                'Aucun indice positif de décalque, de montage ou d’imitation servile n’a été relevé sur les copies examinées. Le rapport conclut à une compatibilité, non à une certitude absolue sur l’original.',
            ],
        ], 6);
        $graphology->addMetadata(new DocumentMetadata('Création du fichier', '12 février 2024 à 16:40', 1));
        $graphology->addMetadata(new DocumentMetadata('Type de fichier', 'PDF signé électroniquement', 2));

        $accountantStatement = new EvidenceDocument('Attestation de l’ancien comptable', 'Attestation sur l’honneur', 'letter', [
            'sender' => 'Émile Caron, ancien comptable de la société',
            'recipient' => 'Cabinet d’expertise documentaire',
            'date' => '22 janvier 2025',
            'subject' => 'Traitement comptable du virement de 45 000 EUR / P-07',
            'paragraphs' => [
                'J’atteste avoir enregistré en juin 2010 la somme de 45 000 EUR au compte « Prêt à terme fixé », sur instruction orale reçue lors d’une réunion des associés.',
                'Je ne peux certifier que cette qualification correspondait à leur accord définitif. Les associés évoquaient également un apport temporaire lié aux travaux.',
                'Je n’ai pas rédigé l’avenant rectificatif mentionné dans le courriel de 2021 et n’en ai jamais conservé de copie.',
            ],
        ], 7);

        $succession = new EvidenceDocument('Attestation successorale', 'Acte successoral', 'contract', [
            'office' => 'Étude notariale de Maître Lenoir',
            'reference' => 'Succession André Vignal / P-08',
            'documentDate' => '8 janvier 2025',
            'place' => 'Niort',
            'clauses' => [
                'André Vignal est décédé le 2 décembre 2024. Sa fille Claire Vignal est désignée héritière dans l’acte de notoriété.',
                'L’attestation autorise l’héritière à représenter la succession dans les démarches patrimoniales.',
                'Cet acte établit la qualité de l’héritière, mais ne certifie pas l’existence ni l’exigibilité de la créance de 45 000 EUR.',
            ],
        ], 8);

        $transmission = new EvidenceDocument('Bordereau des pièces communiquées', 'Bordereau', 'letter', [
            'sender' => 'Étude notariale de Maître Lenoir',
            'recipient' => 'Cabinet d’expertise documentaire',
            'date' => '3 février 2025',
            'subject' => 'Inventaire des pièces reçues pour le dossier du moulin / P-09',
            'paragraphs' => [
                'Pièces reçues : P-01 reconnaissance de dette (copie recto), P-02 relevé bancaire, P-03 statuts, P-04 courriels, P-05 grand livre, P-06 rapport graphologique, P-07 attestation comptable et P-08 acte successoral.',
                'L’avenant rectificatif A-2021-04 mentionné dans P-04 ne figure pas parmi les pièces remises au cabinet. Aucun original complet de P-01 ni annexe recto-verso n’a été transmis.',
                'Ce bordereau décrit le contenu de l’envoi reçu ; il ne permet pas de conclure que l’avenant n’a jamais existé.',
            ],
        ], 9);

        foreach ([$acknowledgement, $bankStatement, $statutes, $correspondence, $ledger, $graphology, $accountantStatement, $succession, $transmission] as $document) {
            $case->addDocument($document);
        }

        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'inconclusive',
            'expectedAnomalies' => [
                [
                    'code' => 'transfer_qualification_conflict',
                    'type' => 'cross_document_inconsistency',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 2, 'selectionKeywords' => ['Apport compte courant d’associé']],
                        ['documentOrder' => 5, 'selectionKeywords' => ['Prêt à terme fixé']],
                    ],
                    'explanation' => 'Le relevé bancaire qualifie le virement d’apport au compte courant, tandis que le grand livre l’enregistre comme prêt à terme fixé. Sans l’avenant évoqué, ces pièces ne permettent pas de déterminer la qualification convenue.',
                ],
                [
                    'code' => 'missing_rectifying_amendment',
                    'type' => 'missing_evidence',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 4, 'selectionKeywords' => ['avenant rectificatif signé en 2021']],
                        ['documentOrder' => 9, 'selectionKeywords' => ['ne figure pas parmi les pièces remises']],
                    ],
                    'acceptedVariants' => [[
                        'type' => 'cross_document_inconsistency',
                        'minimumSelections' => 2,
                        'minimumDistinctDocuments' => 2,
                    ]],
                    'explanation' => 'Les courriels annoncent un avenant rectificatif, tandis que le bordereau indique qu’il n’a pas été communiqué. Ce constat établit une lacune du dossier, pas l’inexistence de l’avenant ni une fraude. Son contenu et la portée de la reconnaissance de dette restent invérifiables.',
                ],
            ],
            'correctionNotes' => [
                'La fraude avérée n’est pas établie : le transfert de 45 000 EUR a bien eu lieu et le rapport graphologique ne relève aucun indice positif de faux sur les copies examinées.',
                'L’authenticité et l’exigibilité de la créance ne sont pas davantage établies : le libellé bancaire et l’écriture du grand livre divergent, tandis que la lacune documentaire empêche de consulter l’avenant évoqué.',
                'La différence de signature entre P-01 et P-03 constitue une fausse piste. Le rapport explique qu’elle est compatible avec une évolution naturelle sur quinze ans ; les originaux n’ayant pas été examinés, cela ne démontre toutefois pas à lui seul toutes les conditions de la dette.',
                'La prudence impose donc une conclusion « Inconcluant » et un complément d’instruction : obtenir l’avenant A-2021-04, l’original complet de P-01 et les justificatifs détaillés de l’écriture comptable.',
            ],
        ]));

        $this->entityManager->persist($case);
        $this->entityManager->flush();

        $io->success('Le Contrat du Moulin a été créé avec neuf pièces, en accès libre, au niveau Pro.');

        return Command::SUCCESS;
    }
}
