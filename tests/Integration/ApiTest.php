<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyMedia\Api\Controller\MediaController;
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

        $data = $this->data($this->service(MediaController::class)->list($this->request('list', ['length' => 10])));

        $this->assertSame(['a.png'], array_column($data['items'], 'name'));
        $this->assertSame(1, $data['pagination']['total']);
    }

    public function testALevelKeepsWhatItsAcceptSays(): void
    {
        $storage = $this->service(MediaStorage::class);
        $storage->import($this->png(), 'photo.png');
        $storage->import($this->text(), 'notes.txt');
        $this->signIn('ROLE_USER');

        $list = fn (string $accept) => array_column($this->data(
            $this->service(MediaController::class)->list($this->request('list', ['length' => 10, 'accept' => $accept]))
        )['items'], 'name');

        $this->assertSame(['photo.png'], $list('image/*'));
        $this->assertSame(['notes.txt'], $list('.TXT'));
        $this->assertSame(['notes.txt', 'photo.png'], $list('image/png,text/plain'));
        $this->assertSame(['notes.txt', 'photo.png'], $list(''));
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
