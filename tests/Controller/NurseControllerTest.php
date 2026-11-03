<?php

namespace App\Tests\Controller;

use App\Controller\NurseController;
use App\Entity\Nurse;
use App\Repository\NurseRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
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

    private function nurse(string $name = 'Ana', string $email = 'ana@test.com', string $password = 'secret-hash'): Nurse
    {
        return (new Nurse())
            ->setName($name)
            ->setEmail($email)
            ->setPassword($password);
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

    private function jsonRequest(mixed $body, string $method = 'POST', string $uri = '/nurse'): Request
    {
        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string($body) ? $body : json_encode($body)
        );
    }

    private function repoFinding(?Nurse $nurse, ?Nurse $emailOwner = null): NurseRepository
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willReturn($nurse);
        $repo->method('findOneBy')->willReturn($emailOwner);

        return $repo;
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

    // ==========================================
    // GET /nurse/{id} (getById)
    // ==========================================

    public function testGetById_Returns200WithoutPassword(): void
    {
        $nurse = $this->nurse();

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

    // ==========================================
    // POST /nurse (create)
    // ==========================================

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

    // ==========================================
    // PUT /nurse/{id} (update)
    // ==========================================

    public function testUpdate_Returns200WithUpdatedNurseWithoutPassword(): void
    {
        $nurse = $this->nurse();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": " Ana María ", "email": "ANA.MARIA@test.com", "password": "newPass123"}', 'PUT'),
            $this->repoFinding($nurse),
            $em
        );
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Ana María', $body['name']);
        $this->assertSame('ana.maria@test.com', $body['email']);
        $this->assertArrayNotHasKey('password', $body);
        $this->assertTrue(password_verify('newPass123', $nurse->getPassword()));
    }

    public function testUpdate_KeepsPasswordWhenNotSent(): void
    {
        $nurse = $this->nurse();

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": "Ana María", "email": "ana@test.com"}', 'PUT'),
            $this->repoFinding($nurse, $nurse),
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('secret-hash', $nurse->getPassword());
    }

    public function testUpdate_Returns204WhenNothingChanges(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": "Ana", "email": "ana@test.com"}', 'PUT'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[DataProvider('invalidIdProvider')]
    public function testUpdate_Returns400ForInvalidId(string $id): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $response = $this->controller()->update(
            $id,
            $this->jsonRequest('{"name": "Ana", "email": "ana@test.com"}', 'PUT'),
            $repo,
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    #[DataProvider('invalidUpdateBodiesProvider')]
    public function testUpdate_Returns400ForMalformedOrEmptyJson(string $body): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest($body, 'PUT'),
            $repo,
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public static function invalidUpdateBodiesProvider(): array
    {
        return [
            'empty string' => [''],
            'malformed' => ['{"name": "Ana",'],
            'empty object' => ['{}'],
            'array' => ['[]'],
            'string' => ['"text"'],
        ];
    }

    public function testUpdate_Returns404WhenNotFound(): void
    {
        $response = $this->controller()->update(
            '999',
            $this->jsonRequest('{"name": "Ana", "email": "ana@test.com"}', 'PUT'),
            $this->repoFinding(null),
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdate_Returns409WhenEmailBelongsToAnotherNurse(): void
    {
        $other = (new Nurse())->setName('Carlos')->setEmail('carlos@test.com')->setPassword('x');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": "Ana", "email": "carlos@test.com"}', 'PUT'),
            $this->repoFinding($this->nurse(), $other),
            $em
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testUpdate_Returns409OnUniqueConstraintViolation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": "Ana", "email": "new@test.com"}', 'PUT'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    #[DataProvider('invalidUpdateFieldsProvider')]
    public function testUpdate_Returns422ForInvalidFields(string $body): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest($body, 'PUT'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public static function invalidUpdateFieldsProvider(): array
    {
        return [
            'missing name' => ['{"email": "ana@test.com"}'],
            'missing email' => ['{"name": "Ana"}'],
            'blank name' => ['{"name": "  ", "email": "ana@test.com"}'],
            'non-string name' => ['{"name": 123, "email": "ana@test.com"}'],
            'invalid email' => ['{"name": "Ana", "email": "not-an-email"}'],
            'weak password' => ['{"name": "Ana", "email": "ana@test.com", "password": "short"}'],
            'non-string password' => ['{"name": "Ana", "email": "ana@test.com", "password": 12345678}'],
        ];
    }

    public function testUpdate_Returns500WhenFlushFails(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(
            new class ('db down') extends \RuntimeException implements ORMException {
            }
        );

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": "Ana", "email": "new@test.com"}', 'PUT'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testUpdate_Returns500WhenLookupFails(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willThrowException(
            new class ('db down') extends \RuntimeException implements ORMException {
            }
        );

        $response = $this->controller()->update(
            '1',
            $this->jsonRequest('{"name": "Ana", "email": "ana@test.com"}', 'PUT'),
            $repo,
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    // ==========================================
    // DELETE /nurse/{id} (delete)
    // ==========================================

    public function testDelete_Returns200OnSuccessfulDeletion(): void
    {
        $nurse = $this->nurse();

        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($nurse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('remove')
            ->with($nurse);
        $em->expects($this->once())
            ->method('flush');

        $response = $this->controller()->delete('1', $repo, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame('Nurse with id 1 was successfully deleted', $body['message']);
    }

    #[DataProvider('invalidIdProvider')]
    public function testDelete_Returns400ForInvalidId(string $id): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->delete($id, $repo, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid id: must be a positive integer', $body['error']);
    }

    public function testDelete_Returns404WhenNotFound(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->delete('999', $repo, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Nurse not found', $body['error']);
    }

    public function testDelete_Returns409WhenForeignKeyConstraintViolation(): void
    {
        $nurse = $this->nurse();

        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->with(1)->willReturn($nurse);

        $exception = $this->createMock(ForeignKeyConstraintViolationException::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($nurse);
        $em->expects($this->once())->method('flush')->willThrowException($exception);

        $response = $this->controller()->delete('1', $repo, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Cannot delete nurse due to existing relational dependencies', $body['error']);
    }

    public function testDelete_Returns500WhenFindThrowsORMException(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willThrowException(
            new class ('db down') extends \RuntimeException implements ORMException {
            }
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->delete('1', $repo, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal server error', $body['error']);
    }

    public function testDelete_Returns500WhenFlushThrowsUnexpectedException(): void
    {
        $nurse = $this->nurse();

        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->with(1)->willReturn($nurse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($nurse);
        $em->expects($this->once())->method('flush')->willThrowException(new \RuntimeException('Connection lost'));

        $response = $this->controller()->delete('1', $repo, $em);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal server error', $body['error']);
    }
}
