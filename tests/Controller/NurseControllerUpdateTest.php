<?php

namespace App\Tests\Controller;

use App\Controller\NurseController;
use App\Entity\Nurse;
use App\Repository\NurseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class NurseControllerUpdateTest extends TestCase
{
    private function controller(): NurseController
    {
        $controller = new NurseController();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function request(string $body): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }

    private function nurse(): Nurse
    {
        return (new Nurse())->setName('Ana')->setEmail('ana@test.com')->setPassword('old-hash');
    }

    private function repoFinding(?Nurse $nurse, ?Nurse $emailOwner = null): NurseRepository
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willReturn($nurse);
        $repo->method('findOneBy')->willReturn($emailOwner);

        return $repo;
    }

    public function testReturns200WithUpdatedNurseWithoutPassword(): void
    {
        $nurse = $this->nurse();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": " Ana María ", "email": "ANA.MARIA@test.com", "password": "newPass123"}'),
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

    public function testKeepsPasswordWhenNotSent(): void
    {
        $nurse = $this->nurse();

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": "Ana María", "email": "ana@test.com"}'),
            $this->repoFinding($nurse, $nurse),
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('old-hash', $nurse->getPassword());
    }

    public function testReturns204WhenNothingChanges(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": "Ana", "email": "ana@test.com"}'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    /** @dataProvider invalidBodies */
    public function testReturns400ForMalformedOrEmptyJson(string $body): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $response = $this->controller()->update('1', $this->request($body), $repo, $this->createMock(EntityManagerInterface::class));

        $this->assertSame(400, $response->getStatusCode());
    }

    public static function invalidBodies(): array
    {
        return [[''], ['{"name": "Ana",'], ['{}'], ['[]'], ['"text"']];
    }

    /** @dataProvider unknownIds */
    public function testReturns404WhenNotFound(string $id): void
    {
        $response = $this->controller()->update(
            $id,
            $this->request('{"name": "Ana", "email": "ana@test.com"}'),
            $this->repoFinding(null),
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public static function unknownIds(): array
    {
        return [['999'], ['0'], ['99999999999999999999']];
    }

    public function testReturns409WhenEmailBelongsToAnotherNurse(): void
    {
        $other = (new Nurse())->setName('Carlos')->setEmail('carlos@test.com')->setPassword('x');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": "Ana", "email": "carlos@test.com"}'),
            $this->repoFinding($this->nurse(), $other),
            $em
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testReturns409OnUniqueConstraintViolation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": "Ana", "email": "new@test.com"}'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    /** @dataProvider invalidFields */
    public function testReturns422ForInvalidFields(string $body): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $response = $this->controller()->update('1', $this->request($body), $this->repoFinding($this->nurse()), $em);

        $this->assertSame(422, $response->getStatusCode());
    }

    public static function invalidFields(): array
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

    public function testReturns500WhenFlushFails(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new class ('db down') extends \RuntimeException implements ORMException {});

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": "Ana", "email": "new@test.com"}'),
            $this->repoFinding($this->nurse()),
            $em
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testReturns500WhenLookupFails(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willThrowException(new class ('db down') extends \RuntimeException implements ORMException {});

        $response = $this->controller()->update(
            '1',
            $this->request('{"name": "Ana", "email": "ana@test.com"}'),
            $repo,
            $this->createMock(EntityManagerInterface::class)
        );

        $this->assertSame(500, $response->getStatusCode());
    }
}
