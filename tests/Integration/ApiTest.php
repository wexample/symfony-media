<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Helper\ApiResponseNormHelper;
use Wexample\SymfonyMedia\Api\Controller\MediaController;
use Wexample\SymfonyMedia\Api\Normalizer\Entity\Media\DefaultMediaNormalizer;
use Wexample\SymfonyMedia\Service\MediaLibraryService;
use Wexample\SymfonyMedia\Service\MediaStorage;

class ApiTest extends AbstractMediaTestCase
{
    private function request(
        string $action,
        array $query = [],
        ?array $body = null
    ): Request {
        $request = null === $body
            ? new Request($query)
            : new Request($query, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body));
        $request->attributes->set('_controller', MediaController::class.'::'.$action);

        return $request;
    }

    private function data(object $response): array
    {
        return json_decode($response->toJsonResponse()->getContent(), true)['data'];
    }

    public function testALevelIsListedWithItsTotal(): void
    {
        $this->service(MediaStorage::class)->import($this->png(), 'a.png');
        $this->signIn('ROLE_USER');

        $data = $this->data($this->service(MediaController::class)->level($this->request('level', ['length' => 10])));

        $this->assertSame(['a.png'], array_column(array_column($data['items'], 'entity'), 'name'));
        $this->assertSame(1, $data['pagination']['total']);
    }

    public function testALevelHoldsToTheNormOfTheApi(): void
    {
        $this->service(MediaLibraryService::class)->createFolder('Photos');
        $this->service(MediaStorage::class)->import($this->png(), 'a.png');
        $this->signIn('ROLE_USER');

        $response = json_decode($this->service(MediaController::class)->level($this->request('level', ['length' => 10]))->toJsonResponse()->getContent(), true);

        $this->assertSame([], ApiResponseNormHelper::problems($response));
        $this->assertSame(['media_folder', 'media'], array_column($response['data']['items'], 'type'));
    }

    public function testALevelKeepsWhatItsAcceptSays(): void
    {
        $storage = $this->service(MediaStorage::class);
        $storage->import($this->png(), 'photo.png');
        $storage->import($this->text(), 'notes.txt');
        $this->signIn('ROLE_USER');

        $list = fn (string $accept) => array_column(array_column($this->data(
            $this->service(MediaController::class)->level($this->request('level', ['length' => 10, 'accept' => $accept]))
        )['items'], 'entity'), 'name');

        $this->assertSame(['photo.png'], $list('image/*'));
        $this->assertSame(['notes.txt'], $list('.TXT'));
        $this->assertSame(['notes.txt', 'photo.png'], $list('image/png,text/plain'));
        $this->assertSame(['notes.txt', 'photo.png'], $list(''));
    }

    public function testTheMediaAreListedAsEntitiesWithWhereTheyAreServed(): void
    {
        $storage = $this->service(MediaStorage::class);
        $photos = $this->service(MediaLibraryService::class)->createFolder('Photos');
        $storage->import($this->png(400, 300), 'b.png', $photos);
        $storage->import($this->text(), 'a.txt');
        $storage->import($this->text(), 'c.pdf')->setRestricted(true);
        $this->entityManager->flush();
        $this->signIn('ROLE_USER');

        $list = function (array $query) {
            $request = $this->request('list', ['length' => 10] + $query);
            $request->attributes->set(SortQueryOption::KEY, 'name');

            return $this->data($this->service(MediaController::class)->list($request, $this->service(DefaultMediaNormalizer::class)));
        };

        $data = $list([]);
        $this->assertSame(['a.txt', 'b.png'], array_column(array_column($data['items'], 'entity'), 'name'), 'a restricted media is not listed');
        $this->assertSame(['media'], array_unique(array_column($data['items'], 'type')));
        $this->assertSame(2, $data['pagination']['total']);

        $image = $data['items'][1];
        $this->assertSame([400, 300], [$image['entity']['width'], $image['entity']['height']]);
        $this->assertStringContainsString($image['entity']['id'], $image['metadata']['url']);
        $this->assertSame(['160', '480'], array_map('strval', array_keys($image['metadata']['thumbnails'])));
        $this->assertSame([], $data['items'][0]['metadata']['thumbnails'], 'a text has no thumbnail');

        $this->assertSame(['b.png'], array_column(array_column($list(['accept' => 'image/*'])['items'], 'entity'), 'name'));
        $this->assertSame(['b.png'], array_column(array_column($list(['folder' => (string) $photos->getId()])['items'], 'entity'), 'name'));

        $this->signIn('ROLE_ADMIN', 'ROLE_USER');
        $this->assertSame(3, $list([])['pagination']['total'], 'whoever manages the library sees it whole');
    }

    public function testTheLibraryIsArrangedByWhoManagesIt(): void
    {
        $controller = $this->service(MediaController::class);
        $this->signIn('ROLE_ADMIN', 'ROLE_USER');

        $photos = $this->data($controller->folder($this->request('folder', body: ['name' => 'Photos'])));
        $inside = $this->data($controller->folder($this->request('folder', body: ['name' => 'Inside', 'parent' => $photos['id']])));
        $this->assertSame('directory', $inside['type']);

        $refused = $controller->delete($this->request('delete', body: ['type' => 'folder', 'id' => $photos['id']]));
        $this->assertSame(400, $refused->getStatus());

        $renamed = $this->data($controller->rename($this->request('rename', body: ['type' => 'folder', 'id' => $inside['id'], 'name' => 'Kept'])));
        $this->assertSame('Kept', $renamed['name']);

        $this->assertSame(200, $controller->delete($this->request('delete', body: ['type' => 'folder', 'id' => $inside['id']]))->getStatus());
    }

    public function testArrangingNeedsTheRoleAndJson(): void
    {
        $controller = $this->service(MediaController::class);

        $this->signIn('ROLE_ADMIN');

        try {
            $controller->folder(new Request(content: '{"name":"x"}'));
            $this->fail('A form post arranged the library.');
        } catch (UnsupportedMediaTypeHttpException) {
        }

        $this->signIn('ROLE_USER');
        $this->expectException(AccessDeniedException::class);
        $controller->folder($this->request('folder', body: ['name' => 'Photos']));
    }
}
