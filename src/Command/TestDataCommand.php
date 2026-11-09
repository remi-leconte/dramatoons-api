<?php

namespace App\Command;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\Webtoon;
use App\Entity\WebtoonTitle;
use App\Entity\WebtoonUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-data',
    description: 'Supprime et recrée le jeu de données de test (comptes test-…, webtoons [TEST] …). Dev uniquement.',
)]
class TestDataCommand extends Command
{
    public const LOGIN_PREFIX = 'test-';
    public const TITLE_PREFIX = '[TEST] ';
    public const PASSWORD = 'test-password';

    /** login => [rôle, compte actif, email vérifié] */
    private const USERS = [
        'test-admin' => ['ROLE_ADMIN', true, true],
        'test-modo' => ['ROLE_MODO', true, true],
        'test-createur' => ['ROLE_USER', true, true],
        'test-lecteur' => ['ROLE_USER', true, true],
        'test-inconnu' => ['ROLE_USER', true, true],
        'test-desactive' => ['ROLE_USER', false, true],
        'test-non-verifie' => ['ROLE_USER', true, false],
    ];

    /**
     * titre => [publié, statut, suivis : login => [state, bookmark, rate]]
     * Tous sont créés par test-createur.
     */
    private const WEBTOONS = [
        'Publié - sans suivi' => [true, 'ongoing', []],
        'Publié - suivi par le lecteur' => [true, 'completed', ['test-lecteur' => ['reading', 3, 7.5]]],
        'Dépublié - sans suivi' => [false, 'ongoing', []],
        'Dépublié - suivi par le créateur seul' => [false, 'completed', ['test-createur' => ['reading', 5, 8.0]]],
        'Dépublié - suivi par le lecteur' => [false, 'ongoing', ['test-lecteur' => ['pause', 12, 6.0]]],
        'Suivi - en cours' => [true, 'ongoing', ['test-lecteur' => ['reading', 10, null]]],
        'Suivi - en pause' => [true, 'completed', ['test-lecteur' => ['pause', 4, null]]],
        'Suivi - pas intéressé' => [true, 'ongoing', ['test-lecteur' => ['break', null, null]]],
        'Suivi - terminé' => [true, 'completed', ['test-lecteur' => ['completed', 120, 9.0]]],
        'Suivi - chapitre seul' => [true, 'ongoing', ['test-lecteur' => [null, 7, null]]],
        'Suivi - note seule' => [true, 'completed', ['test-lecteur' => [null, null, 4.5]]],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')]
        private string $environment
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Le mot de passe des comptes de test est public : ces comptes ne doivent jamais exister ailleurs qu'en dev
        if ('dev' !== $this->environment) {
            $io->error(sprintf('Commande réservée à l\'environnement dev (environnement actuel : %s).', $this->environment));
            return Command::FAILURE;
        }

        $this->deleteTestData();
        $users = $this->createUsers();
        $this->createWebtoons($users);

        $io->success(sprintf(
            '%d comptes et %d webtoons de test recréés. Mot de passe : %s',
            count(self::USERS),
            count(self::WEBTOONS),
            self::PASSWORD
        ));

        return Command::SUCCESS;
    }

    private function deleteTestData(): void
    {
        // Un webtoon [TEST] peut avoir changé de créateur pendant un test (compte supprimé → « Utilisateur supprimé »)
        $webtoons = $this->findByPrefix(
            $this->em->createQueryBuilder()
                ->select('w')
                ->from(Webtoon::class, 'w')
                ->leftJoin('w.creator', 'c')
                ->leftJoin('w.title', 't')
                ->where('c.login LIKE :login OR t.title LIKE :title')
                ->setParameter('login', self::LOGIN_PREFIX . '%')
                ->setParameter('title', self::TITLE_PREFIX . '%'),
            fn(Webtoon $w) => $this->isTestLogin($w->getCreator()?->getLogin())
                || str_starts_with((string) $w->getTitle()?->getTitle(), self::TITLE_PREFIX)
        );
        $this->removeAll($webtoons);

        $this->removeAll($this->findByPrefix(
            $this->em->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.login LIKE :login')
                ->setParameter('login', self::LOGIN_PREFIX . '%'),
            fn(User $u) => $this->isTestLogin($u->getLogin())
        ));

        // Un refresh token est lié au login : sans suppression, il resterait valable pour le compte recréé
        $this->removeAll($this->findByPrefix(
            $this->em->createQueryBuilder()
                ->select('r')
                ->from(RefreshToken::class, 'r')
                ->where('r.username LIKE :login')
                ->setParameter('login', self::LOGIN_PREFIX . '%'),
            fn(RefreshToken $r) => $this->isTestLogin($r->getUsername())
        ));
    }

    /**
     * Les collations de la base ignorent casse et accents (« Test-Marie » LIKE « test-% ») :
     * le LIKE ne sert qu'à présélectionner, le préfixe exact est vérifié en PHP.
     *
     * @param callable(object): bool $isTestData
     * @return list<object>
     */
    private function findByPrefix(QueryBuilder $qb, callable $isTestData): array
    {
        return array_values(array_filter($qb->getQuery()->getResult(), $isTestData));
    }

    private function isTestLogin(?string $login): bool
    {
        return null !== $login && str_starts_with($login, self::LOGIN_PREFIX);
    }

    /**
     * @param list<object> $entities
     */
    private function removeAll(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->em->remove($entity);
        }
        $this->em->flush();
    }

    /**
     * @return array<string, User>
     */
    private function createUsers(): array
    {
        $users = [];

        foreach (self::USERS as $login => [$role, $publish, $verified]) {
            $user = new User();
            $user->setLogin($login);
            $user->setEmail($login . '@dramatoons.test');
            $user->setRoles([$role]);
            $user->setPublish($publish);
            $user->setVerified($verified);
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));

            $this->em->persist($user);
            $users[$login] = $user;
        }
        $this->em->flush();

        return $users;
    }

    /**
     * @param array<string, User> $users
     */
    private function createWebtoons(array $users): void
    {
        foreach (self::WEBTOONS as $title => [$publish, $status, $follows]) {
            $webtoon = new Webtoon();
            $webtoon->setCreator($users['test-createur']);
            $webtoon->setPublish($publish);
            $webtoon->setStatus($status);

            $mainTitle = (new WebtoonTitle())->setTitle(self::TITLE_PREFIX . $title);
            $mainTitle->setWebtoon($webtoon);
            $webtoon->addSecondaryTitle($mainTitle);
            $webtoon->setTitle($mainTitle);

            $secondaryTitle = (new WebtoonTitle())->setTitle(self::TITLE_PREFIX . $title . ' (titre secondaire)');
            $secondaryTitle->setWebtoon($webtoon);
            $webtoon->addSecondaryTitle($secondaryTitle);

            $this->em->persist($webtoon);

            foreach ($follows as $login => [$state, $bookmark, $rate]) {
                $follow = new WebtoonUser();
                $follow->setReader($users[$login]);
                $follow->setWebtoon($webtoon);
                $follow->setState($state);
                $follow->setBookmark($bookmark);
                $follow->setRate($rate);

                $this->em->persist($follow);
            }
        }

        // Les statistiques (note moyenne, nombre de lecteurs) sont recalculées par WebtoonUserListener
        $this->em->flush();
    }
}
