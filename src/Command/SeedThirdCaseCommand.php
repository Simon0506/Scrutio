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

#[AsCommand(name: 'app:case:seed-third', description: 'Créer l’affaire gratuite La Presse des Tilleuls.')]
final class SeedThirdCaseCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->entityManager->getRepository(CaseFile::class)->findOneBy(['slug' => 'la-presse-des-tilleuls']) instanceof CaseFile) {
            $io->success('La Presse des Tilleuls existe déjà.');

            return Command::SUCCESS;
        }

        $case = new CaseFile(
            'La Presse des Tilleuls',
            'la-presse-des-tilleuls',
            'Une association prépare l’exposition d’une presse typographique acquise auprès d’un atelier. Plusieurs écarts dans les documents transmis ont suspendu son entrée à l’inventaire. Le cabinet doit examiner l’identité de la machine, la transaction et son acheminement avant de rendre son avis sur le dossier.',
            'Intermédiaire',
            displayOrder: 3,
        );

        $inventory = new EvidenceDocument('Fiche d’inventaire de l’atelier', 'Inventaire matériel', 'contract', [
            'office' => 'Atelier des Tilleuls',
            'reference' => 'Inventaire AT-042',
            'documentDate' => '3 février 2025',
            'place' => 'Tours',
            'clauses' => [
                'Presse typographique manuelle, numéro de série gravé PT-1932-084, année de fabrication portée sur la plaque : 1932.',
                'Dimensions de la machine montée : largeur 82 cm, profondeur 110 cm, hauteur 146 cm.',
                'La table latérale est démontable. Une reprise de peinture grise autour du socle a été effectuée en 2018 ; la plaque et le numéro gravé sont restés en place.',
            ],
        ], 1);

        $invoice = new EvidenceDocument('Facture de cession', 'Facture', 'contract', [
            'office' => 'Atelier des Tilleuls',
            'reference' => 'Facture F-2025-017 / AT-042',
            'documentDate' => '6 février 2025',
            'place' => 'Tours',
            'clauses' => [
                'Acquéreur : Association Mémoire imprimée. Cession de la presse PT-1932-084 avec sa table latérale.',
                'Prix hors taxes : 1 200,00 EUR. TVA à 20 % : 240,00 EUR. Total TTC : 1 440,00 EUR.',
                'Acompte de 360,00 EUR reçu le 4 février 2025 sur réservation du 31 janvier. Solde à régler avant enlèvement : 1 080,00 EUR.',
            ],
        ], 2);

        $payments = new EvidenceDocument('Relevé des règlements', 'Relevé comptable', 'contract', [
            'office' => 'Association Mémoire imprimée / Trésorerie',
            'reference' => 'Extrait du journal bancaire JB-2025-02',
            'documentDate' => '12 février 2025',
            'place' => 'Blois',
            'clauses' => [
                '4 février 2025 : virement de 360,00 EUR à l’Atelier des Tilleuls, libellé réservation AT-042.',
                '10 février 2025 : virement de 1 080,00 EUR au même bénéficiaire, libellé solde F-2025-017.',
                'Les deux écritures sont comptabilisées au débit du compte de l’association et portent le statut exécuté.',
            ],
        ], 3);

        $transport = new EvidenceDocument('Bon de transport', 'Transport', 'contract', [
            'office' => 'Transports Loire Manutention',
            'reference' => 'Lettre de voiture LM-250211 / AT-042',
            'documentDate' => '11 février 2025',
            'place' => 'Tours',
            'clauses' => [
                'Enlèvement à l’Atelier des Tilleuls le 11 février 2025. Destination : Association Mémoire imprimée, Blois.',
                'Colis 1 : presse PT-1932-084, table latérale déposée, dimensions hors emballage 62 x 110 x 146 cm.',
                'Colis 2 : table latérale de 20 cm de largeur, étiquetée AT-042. Les deux colis voyagent sous la même lettre de voiture.',
            ],
        ], 4);

        $receipt = new EvidenceDocument('Constat de réception', 'Constat matériel', 'contract', [
            'office' => 'Association Mémoire imprimée / Commission des collections',
            'reference' => 'Réception RC-2025-009 / LM-250211',
            'documentDate' => '12 février 2025',
            'place' => 'Blois',
            'clauses' => [
                'Deux colis reçus : presse et table latérale. Le numéro gravé PT-1932-084 correspond à celui de la fiche AT-042 et de la facture F-2025-017.',
                'Après remontage de la table, mesures relevées : 82 x 110 x 146 cm. Aucun manque matériel constaté.',
                'La peinture grise du socle présente une patine différente de celle du bâti. La plaque constructeur reste lisible et ne montre ni surfrappe ni arrachement.',
            ],
        ], 5);

        $coverLetter = new EvidenceDocument('Courrier de versement aux archives', 'Courrier', 'letter', [
            'sender' => 'Association Mémoire imprimée / Secrétariat',
            'recipient' => 'Cabinet d’expertise documentaire',
            'date' => '18 mars 2025',
            'subject' => 'Entrée à l’inventaire de la presse AT-042',
            'paragraphs' => [
                'Les cinq pièces jointes ont été numérisées le 17 mars 2025 à partir des originaux papier conservés au siège. Les fichiers ont été regroupés le même jour pour ce versement.',
                'La commission demande un avis sur la cohérence du dossier avant son classement définitif. La machine est conservée dans l’état décrit au constat de réception.',
            ],
        ], 6);

        foreach ([$inventory, $invoice, $payments, $transport, $receipt] as $document) {
            $document->addMetadata(new DocumentMetadata('Création du fichier', '17 mars 2025 à 14:20', 1));
            $document->addMetadata(new DocumentMetadata('Logiciel émetteur', 'NAPS2 / numérisation PDF', 2));
        }

        foreach ([$inventory, $invoice, $payments, $transport, $receipt, $coverLetter] as $document) {
            $case->addDocument($document);
        }

        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'authentic',
            'expectedAnomalies' => [],
            'correctionNotes' => [
                'Le numéro PT-1932-084 et les références AT-042, F-2025-017 et LM-250211 relient les pièces à une même machine et à une même transaction.',
                'Les règlements de 360 EUR et 1 080 EUR couvrent exactement les 1 440 EUR TTC. L’acompte antérieur à la facture correspond à la réservation mentionnée sur celle-ci.',
                'La différence de largeur résulte du démontage de la table : 62 cm pour la presse seule, puis 82 cm après remontage. Le bon de transport mentionne bien un second colis de 20 cm.',
                'La peinture différente est cohérente avec la reprise du socle déclarée à l’inventaire. Le constat ne décrit aucune altération du numéro ni de la plaque.',
                'Les pièces concordent dans le périmètre de cet examen documentaire. La conclusion authentique concerne ce dossier, sans constituer une expertise physique indépendante de la machine.',
            ],
            'metadataCaution' => 'La création des fichiers en mars correspond à la numérisation des originaux de février, expliquée par le courrier de versement. Elle ne date pas les actes eux-mêmes.',
        ]));

        $this->entityManager->persist($case);
        $this->entityManager->flush();
        $io->success('La Presse des Tilleuls a été créée avec six pièces, en accès libre.');

        return Command::SUCCESS;
    }
}
