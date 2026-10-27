<?php

namespace App\Tests\Controller;

use App\Controller\NurseController;
use App\Entity\Nurse;
use App\Repository\NurseRepository;
use Doctrine\ORM\Exception\ORMException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class NurseControllerGetByIdTest extends TestCase
{
    private function controller(): NurseController
    {
        $controller = new NurseController();
        $controller->setContainer(new Container());

        return $controller;
    }

    public function testReturns200WithoutPassword(): void
    {
        $nurse = (new Nurse())->setName('Ana')->setEmail('ana@test.com')->setPassword('secret-hash');
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->with(1)->willReturn($nurse);

        $response = $this->controller()->getById('1', $repo);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Ana', $body['name']);
        $this->assertArrayNotHasKey('password', $body);
    }

    /** @dataProvider invalidIds */
    public function testReturns400ForInvalidId(string $id): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $this->assertSame(400, $this->controller()->getById($id, $repo)->getStatusCode());
    }

    public static function invalidIds(): array
    {
        return [['abc'], ['-1'], ['0'], ['1.5'], ['99999999999999999999']];
    }

    public function testReturns404WhenNotFound(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willReturn(null);

        $this->assertSame(404, $this->controller()->getById('999', $repo)->getStatusCode());
    }

    public function testReturns500OnDatabaseFailure(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willThrowException(new class('db down') extends \RuntimeException implements ORMException {});

        $this->assertSame(500, $this->controller()->getById('1', $repo)->getStatusCode());
    }
}
