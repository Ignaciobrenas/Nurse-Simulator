<?php

namespace App\Tests\Controller;

use App\Controller\NurseController;
use App\Entity\Nurse;
use App\Repository\NurseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class NurseControllerTest extends TestCase
{
    private function controller(): NurseController
    {
        $controller = new NurseController();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function buildEntityManager(
        ?Nurse $existingNurse = null,
        bool $flushThrows = false
    ): EntityManagerInterface {
        $nurseRepo = $this->createMock(EntityRepository::class);
        $nurseRepo
            ->method('findOneBy')
            ->willReturn($existingNurse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($nurseRepo);
        $em->method('persist');

        if ($flushThrows) {
            $em->method('flush')->willThrowException(new \RuntimeException('DB down'));
        } else {
            $em->method('flush');
        }

        return $em;
    }

    private function jsonRequest(mixed $body): Request
    {
        return Request::create(
            '/nurse',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string($body) ? $body : json_encode($body)
        );
    }

    public function testGetById_Returns200WithoutPassword(): void
    {
        $nurse = (new Nurse())
            ->setName('Ana')
            ->setEmail('ana@test.com')
            ->setPassword('secret-hash');

        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->with(1)->willReturn($nurse);

        $response = $this->controller()->getById('1', $repo);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Ana', $body['name']);
        $this->assertArrayNotHasKey('password', $body);
    }

    #[DataProvider('invalidIdProvider')]
    public function testGetById_Returns400ForInvalidId(string $id): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $this->assertSame(400, $this->controller()->getById($id, $repo)->getStatusCode());
    }

    public static function invalidIdProvider(): array
    {
        return [
            'letras' => ['abc'],
            'negativo' => ['-1'],
            'cero' => ['0'],
            'decimal' => ['1.5'],
            'demasiado grande' => ['99999999999999999999'],
        ];
    }

    public function testGetById_Returns404WhenNotFound(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willReturn(null);

        $this->assertSame(404, $this->controller()->getById('999', $repo)->getStatusCode());
    }

    public function testGetById_Returns500OnDatabaseFailure(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willThrowException(
            new class ('db down') extends \RuntimeException implements ORMException {
            }
        );

        $this->assertSame(500, $this->controller()->getById('1', $repo)->getStatusCode());
    }

    public function testCreate_Returns201OnSuccess(): void
    {
        $em = $this->buildEntityManager(existingNurse: null);

        $request = $this->jsonRequest([
            'name' => 'María',
            'email' => 'maria@example.com',
            'password' => 'Segura123',
        ]);
        $response = $this->controller()->create($request, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('name', $body);
        $this->assertArrayHasKey('email', $body);
        $this->assertArrayNotHasKey('password', $body);
        $this->assertSame('María', $body['name']);
        $this->assertSame('maria@example.com', $body['email']);
    }

    public function testCreate_Returns400ForMalformedJson(): void
    {
        $em = $this->buildEntityManager();
        $request = $this->jsonRequest('{not valid json}');
        $response = $this->controller()->create($request, $em);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[DataProvider('missingFieldProvider')]
    public function testCreate_Returns400ForMissingOrEmptyFields(array $payload): void
    {
        $em = $this->buildEntityManager();
        $request = $this->jsonRequest($payload);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(400, $response->getStatusCode());
    }

    public static function missingFieldProvider(): array
    {
        return [
            'sin name' => [['email' => 'a@b.com', 'password' => 'Pass1234']],
            'sin email' => [['name' => 'Ana', 'password' => 'Pass1234']],
            'sin password' => [['name' => 'Ana', 'email' => 'a@b.com']],
            'name vacío' => [['name' => '   ', 'email' => 'a@b.com', 'password' => 'Pass1234']],
            'email vacío' => [['name' => 'Ana', 'email' => '', 'password' => 'Pass1234']],
            'password vacío' => [['name' => 'Ana', 'email' => 'a@b.com', 'password' => '']],
        ];
    }

    public function testCreate_Returns409WhenEmailAlreadyExists(): void
    {
        $existing = (new Nurse())->setName('Otro')->setEmail('dup@example.com')->setPassword('hash');
        $em = $this->buildEntityManager(existingNurse: $existing);

        $request = $this->jsonRequest([
            'name' => 'Nueva',
            'email' => 'dup@example.com',
            'password' => 'Valida123',
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testCreate_Returns422ForInvalidEmailFormat(): void
    {
        $em = $this->buildEntityManager();
        $request = $this->jsonRequest([
            'name' => 'Ana',
            'email' => 'no-es-un-email',
            'password' => 'Valida123',
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[DataProvider('weakPasswordProvider')]
    public function testCreate_Returns422ForWeakPassword(string $password): void
    {
        $em = $this->buildEntityManager();
        $request = $this->jsonRequest([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => $password,
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(422, $response->getStatusCode());
    }

    public static function weakPasswordProvider(): array
    {
        return [
            'muy corta' => ['Ab1'],
            'sin números' => ['SoloLetras'],
            'sin letras' => ['12345678'],
        ];
    }

    public function testCreate_Returns500OnDatabaseFailure(): void
    {
        $em = $this->buildEntityManager(existingNurse: null, flushThrows: true);
        $request = $this->jsonRequest([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'Valida123',
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(500, $response->getStatusCode());
    }
}
