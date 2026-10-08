<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class AbstractMediaTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected string $storageDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->storageDir = self::getContainer()->getParameter('wexample_symfony_media.storage_dir');
        (new Filesystem())->remove($this->storageDir);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $class): object
    {
        return self::getContainer()->get($class);
    }

    /**
     * A png of $width × $height in a temporary file, as an upload leaves it.
     */
    protected function png(
        int $width = 400,
        int $height = 200
    ): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));
        $path = tempnam(sys_get_temp_dir(), 'media').'.png';
        imagepng($image, $path);

        return $path;
    }

    protected function text(string $content = 'hello'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'media');
        file_put_contents($path, $content);

        return $path;
    }

    protected function signIn(string ...$roles): void
    {
        self::getContainer()->get('security.token_storage')->setToken(
            new UsernamePasswordToken(new InMemoryUser('someone', null, $roles), 'main', $roles)
        );
    }
}
