<?php

namespace App\Tests\Api\Webtoon;

use App\Entity\User;
use App\Entity\Webtoon;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[TestDox('Couverture d’un webtoon (POST /webtoons/{id}/cover) : JPEG de 1 Mo au maximum')]
final class WebtoonCouvertureTest extends ApiTestCase
{
    /** En-tête JPEG (JFIF) : suffit à faire reconnaître le fichier comme image/jpeg */
    private const ENTETE_JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    /** @var list<string> */
    private array $fichiersTemporaires = [];

    protected function tearDown(): void
    {
        // Un fichier refusé n'est pas déplacé par VichUploader : il resterait dans le dossier temporaire
        foreach ($this->fichiersTemporaires as $chemin) {
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }
        $this->fichiersTemporaires = [];

        parent::tearDown();
    }

    private function fichier(string $contenu, string $nom): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'couverture');
        file_put_contents($chemin, $contenu);
        $this->fichiersTemporaires[] = $chemin;

        return new UploadedFile($chemin, $nom, null, null, true);
    }

    private function envoyer(?User $utilisateur, Webtoon $webtoon, ?UploadedFile $fichier): ResponseInterface
    {
        return $this->client($utilisateur)->request('POST', '/webtoons/'.$webtoon->getId().'/cover', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => ['files' => null === $fichier ? [] : ['file' => $fichier]],
        ]);
    }

    private function jpeg(int $taille = 1000): UploadedFile
    {
        return $this->fichier(self::ENTETE_JPEG.str_repeat("\x00", $taille - \strlen(self::ENTETE_JPEG)), 'couverture.jpg');
    }

    #[TestDox('Un visiteur non connecté ne peut pas envoyer de couverture : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $this->envoyer(null, WebtoonFactory::createOne(), $this->jpeg());

        $this->assertResponseStatusCodeSame(401);
    }

    private function chemin(string $image): string
    {
        return static::getContainer()->getParameter('kernel.project_dir').'/var/test/upload/cover/'.$image;
    }

    #[TestDox('Le créateur d’un webtoon dépublié que personne ne suit envoie un JPEG : enregistré sous un nom unique')]
    public function testCreateurEnvoieJpeg(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $image = $this->envoyer($createur, $webtoon, $this->jpeg())->toArray()['image'];

        $this->assertResponseStatusCodeSame(201);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+\.jpg$/', $image);
        $this->assertFileExists($this->chemin($image));
    }

    #[TestDox('Une couverture envoyée juste après la création du webtoon est enregistrée, et un nouvel envoi aussitôt après la remplace sous un autre nom')]
    public function testCouvertureJusteApresCreation(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $premiere = $this->envoyer($createur, $webtoon, $this->jpeg(1000))->toArray()['image'];
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(1000, filesize($this->chemin($premiere)));

        $seconde = $this->envoyer($createur, $webtoon, $this->jpeg(2000))->toArray()['image'];
        $this->assertResponseStatusCodeSame(201);
        $this->assertNotSame($premiere, $seconde);
        $this->assertSame(2000, filesize($this->chemin($seconde)));
        $this->assertFileDoesNotExist($this->chemin($premiere));
    }

    #[TestDox('Deux webtoons de même titre, ou de titre non latin, ont chacun leur couverture')]
    public function testCouverturesDeWebtoonsDeMemeTitre(): void
    {
        $createur = UserFactory::createOne();
        $images = [];
        foreach (['Solo Leveling', 'Solo Leveling', '나 혼자만 레벨업', '進撃の巨人'] as $i => $titre) {
            $id = $this->post($createur, '/webtoons', ['title' => ['title' => $titre], 'status' => 'ongoing'])->toArray()['id'];
            $images[] = $this->envoyer($createur, WebtoonFactory::repository()->find($id), $this->jpeg(1000 + $i))->toArray()['image'];
            $this->assertResponseStatusCodeSame(201);
        }

        $this->assertCount(4, array_unique($images));
        foreach ($images as $i => $image) {
            $this->assertSame(1000 + $i, filesize($this->chemin($image)));
        }
    }

    #[TestDox('Le créateur d’un webtoon publié ne peut pas changer sa couverture : 403')]
    public function testCreateurWebtoonPublie(): void
    {
        $createur = UserFactory::createOne();

        $this->envoyer($createur, WebtoonFactory::new()->publie()->create(['creator' => $createur]), $this->jpeg());

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un utilisateur ne peut pas changer la couverture du webtoon d’un autre : 403')]
    public function testAutreUtilisateur(): void
    {
        $this->envoyer(UserFactory::createOne(), WebtoonFactory::new()->publie()->create(), $this->jpeg());

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un modérateur change la couverture d’un webtoon publié')]
    public function testModerateur(): void
    {
        $this->envoyer(UserFactory::new()->moderateur()->create(), WebtoonFactory::new()->publie()->create(), $this->jpeg());

        $this->assertResponseStatusCodeSame(201);
    }

    #[TestDox('Une image PNG est refusée : 422')]
    public function testPngRefuse(): void
    {
        $createur = UserFactory::createOne();
        $png = $this->fichier("\x89PNG\r\n\x1A\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xDE", 'couverture.png');

        $this->envoyer($createur, WebtoonFactory::createOne(['creator' => $createur]), $png);

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Un fichier texte nommé .jpg est refusé : 422')]
    public function testFauxJpegRefuse(): void
    {
        $createur = UserFactory::createOne();

        $this->envoyer($createur, WebtoonFactory::createOne(['creator' => $createur]), $this->fichier('pas une image', 'couverture.jpg'));

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Un JPEG de plus de 1 Mo est refusé : 422')]
    public function testJpegTropGros(): void
    {
        $createur = UserFactory::createOne();

        $this->envoyer($createur, WebtoonFactory::createOne(['creator' => $createur]), $this->jpeg(1_000_001));

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Un envoi sans fichier est refusé : 422')]
    public function testSansFichier(): void
    {
        $createur = UserFactory::createOne();

        $this->envoyer($createur, WebtoonFactory::createOne(['creator' => $createur]), null);

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Le créateur d’un webtoon dépublié suivi par un autre utilisateur ne peut pas changer sa couverture : 403')]
    public function testCreateurWebtoonSuiviParUnAutre(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        WebtoonUserFactory::createOne(['webtoon' => $webtoon]);

        $this->envoyer($createur, $webtoon, $this->jpeg());

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un lecteur qui suit un webtoon dépublié dont il n’est pas le créateur ne peut pas changer sa couverture : 403')]
    public function testLecteurWebtoonDepublieSuivi(): void
    {
        $suivi = WebtoonUserFactory::createOne();

        $this->envoyer($suivi->getReader(), $suivi->getWebtoon(), $this->jpeg());

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un administrateur change la couverture d’un webtoon publié')]
    public function testAdministrateur(): void
    {
        $this->envoyer(UserFactory::new()->administrateur()->create(), WebtoonFactory::new()->publie()->create(), $this->jpeg());

        $this->assertResponseStatusCodeSame(201);
    }

    #[TestDox('Un JPEG d’exactement 1 Mo (1 000 000 octets) est accepté')]
    public function testJpegDeUnMo(): void
    {
        $createur = UserFactory::createOne();

        $this->envoyer($createur, WebtoonFactory::createOne(['creator' => $createur]), $this->jpeg(1_000_000));

        $this->assertResponseStatusCodeSame(201);
    }
}
