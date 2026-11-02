<?php

namespace App\Tests\Controller;

use App\Controller\NurseController;
use App\Entity\Nurse;
use App\Repository\NurseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests unitarios para NurseController.
 *
 * Cubre todos los endpoints:
 *   - GET  /nurse/{id}  → 200, 400, 404, 500
 *   - POST /nurse       → 201, 400, 409, 422, 500
 */
class NurseControllerTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /** Devuelve un NurseController con un contenedor vacío inyectado. */
    private function controller(): NurseController
    {
        $controller = new NurseController();
        $controller->setContainer(new Container());

        return $controller;
    }

    /**
     * Crea un EntityManagerInterface mock preconfigurado.
     *
     * @param Nurse|null $existingNurse  Nurse que devuelve findOneBy (null = email libre)
     * @param bool       $flushThrows   Si true, flush() lanza \RuntimeException (simula fallo de BD)
     */
    private function buildEntityManager(
        ?Nurse $existingNurse = null,
        bool $flushThrows = false
    ): EntityManagerInterface {
        // Repositorio interno que usa el controller para comprobar duplicados
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

    /** Crea un Request POST con el body JSON indicado. */
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

    // ═════════════════════════════════════════════════════════════
    //  GET /nurse/{id}
    // ═════════════════════════════════════════════════════════════

    /**
     * 200 – Nurse encontrada; la respuesta contiene el nombre y NO la contraseña.
     */
    public function testGetById_Returns200WithoutPassword(): void
    {
        $nurse = (new Nurse())
            ->setName('Ana')
            ->setEmail('ana@test.com')
            ->setPassword('secret-hash');

        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->with(1)->willReturn($nurse);

        $response = $this->controller()->getById('1', $repo);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Ana', $body['name']);
        $this->assertArrayNotHasKey('password', $body);
    }

    /**
     * 400 – IDs no válidos (letras, negativos, cero, decimal, demasiado grande).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIdProvider')]
    public function testGetById_Returns400ForInvalidId(string $id): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->expects($this->never())->method('find');

        $this->assertSame(400, $this->controller()->getById($id, $repo)->getStatusCode());
    }

    public static function invalidIdProvider(): array
    {
        return [
            'letras'          => ['abc'],
            'negativo'        => ['-1'],
            'cero'            => ['0'],
            'decimal'         => ['1.5'],
            'demasiado grande'=> ['99999999999999999999'],
        ];
    }

    /**
     * 404 – La nurse no existe en base de datos.
     */
    public function testGetById_Returns404WhenNotFound(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willReturn(null);

        $this->assertSame(404, $this->controller()->getById('999', $repo)->getStatusCode());
    }

    /**
     * 500 – El repositorio lanza una excepción de BD/ORM.
     */
    public function testGetById_Returns500OnDatabaseFailure(): void
    {
        $repo = $this->createMock(NurseRepository::class);
        $repo->method('find')->willThrowException(
            new class('db down') extends \RuntimeException implements ORMException {}
        );

        $this->assertSame(500, $this->controller()->getById('1', $repo)->getStatusCode());
    }

    // ═════════════════════════════════════════════════════════════
    //  POST /nurse  (create)
    // ═════════════════════════════════════════════════════════════

    /**
     * 201 – Nurse creada correctamente; la respuesta contiene id, name, email
     *       y NO incluye la contraseña.
     */
    public function testCreate_Returns201OnSuccess(): void
    {
        $em = $this->buildEntityManager(existingNurse: null);

        $request  = $this->jsonRequest([
            'name'     => 'María',
            'email'    => 'maria@example.com',
            'password' => 'Segura123',
        ]);
        $response = $this->controller()->create($request, $em);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('name', $body);
        $this->assertArrayHasKey('email', $body);
        $this->assertArrayNotHasKey('password', $body);
        $this->assertSame('María', $body['name']);
        $this->assertSame('maria@example.com', $body['email']);
    }

    /**
     * 400 – JSON con sintaxis incorrecta.
     */
    public function testCreate_Returns400ForMalformedJson(): void
    {
        $em       = $this->buildEntityManager();
        $request  = $this->jsonRequest('{not valid json}');
        $response = $this->controller()->create($request, $em);

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * 400 – Campos requeridos ausentes o vacíos.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('missingFieldProvider')]
    public function testCreate_Returns400ForMissingOrEmptyFields(array $payload): void
    {
        $em       = $this->buildEntityManager();
        $request  = $this->jsonRequest($payload);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(400, $response->getStatusCode());
    }

    public static function missingFieldProvider(): array
    {
        return [
            'sin name'      => [['email' => 'a@b.com', 'password' => 'Pass1234']],
            'sin email'     => [['name'  => 'Ana',     'password' => 'Pass1234']],
            'sin password'  => [['name'  => 'Ana',     'email'    => 'a@b.com' ]],
            'name vacío'    => [['name'  => '   ',     'email'    => 'a@b.com',  'password' => 'Pass1234']],
            'email vacío'   => [['name'  => 'Ana',     'email'    => '',         'password' => 'Pass1234']],
            'password vacío'=> [['name'  => 'Ana',     'email'    => 'a@b.com',  'password' => '']],
        ];
    }

    /**
     * 409 – El email ya está registrado en la base de datos.
     */
    public function testCreate_Returns409WhenEmailAlreadyExists(): void
    {
        $existing = (new Nurse())->setName('Otro')->setEmail('dup@example.com')->setPassword('hash');
        $em       = $this->buildEntityManager(existingNurse: $existing);

        $request  = $this->jsonRequest([
            'name'     => 'Nueva',
            'email'    => 'dup@example.com',
            'password' => 'Valida123',
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(409, $response->getStatusCode());
    }

    /**
     * 422 – Email con formato inválido.
     */
    public function testCreate_Returns422ForInvalidEmailFormat(): void
    {
        $em       = $this->buildEntityManager();
        $request  = $this->jsonRequest([
            'name'     => 'Ana',
            'email'    => 'no-es-un-email',
            'password' => 'Valida123',
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(422, $response->getStatusCode());
    }

    /**
     * 422 – Contraseña que no cumple los requisitos de seguridad.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('weakPasswordProvider')]
    public function testCreate_Returns422ForWeakPassword(string $password): void
    {
        $em       = $this->buildEntityManager();
        $request  = $this->jsonRequest([
            'name'     => 'Ana',
            'email'    => 'ana@example.com',
            'password' => $password,
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(422, $response->getStatusCode());
    }

    public static function weakPasswordProvider(): array
    {
        return [
            'muy corta'          => ['Ab1'],
            'sin números'        => ['SoloLetras'],
            'sin letras'         => ['12345678'],
        ];
    }

    /**
     * 500 – flush() falla (p. ej. BD caída).
     */
    public function testCreate_Returns500OnDatabaseFailure(): void
    {
        $em       = $this->buildEntityManager(existingNurse: null, flushThrows: true);
        $request  = $this->jsonRequest([
            'name'     => 'Ana',
            'email'    => 'ana@example.com',
            'password' => 'Valida123',
        ]);
        $response = $this->controller()->create($request, $em);

        $this->assertSame(500, $response->getStatusCode());
    }
}
