<?php

namespace App\Controller;

use App\Repository\NurseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Nurse;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class NurseController extends AbstractController
{
    #[Route('/nurse/name/{name}', name: 'nurse_find_by_name', methods: ['GET'])]
    public function findByName(string $name, NurseRepository $nurseRepository): JsonResponse
    {
        $nurse = $nurseRepository->findByName($name);

        if ($nurse === null) {
            return $this->json(['error' => 'Nurse not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($nurse);
    }

    #[Route('/nurse/index', name: 'nurse_index', methods: ['GET'])]
    public function getAll(NurseRepository $nurseRepository): JsonResponse
    {

        //utilizando la Funcion de doctrine findAll()
        $nurses = $nurseRepository->findAll() ;

        return $this->json($nurses);
    }

    #[Route('/nurse/login', name: 'nurse_login', methods: ['POST'])]
    public function login(Request $request, NurseRepository $nurseRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return $this->json(['success' => false, 'message' => 'Username and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $nurse = $nurseRepository->findByUsername($username);

        if ($nurse === null) {
            return $this->json(['success' => false], Response::HTTP_UNAUTHORIZED);
        }

        $storedPassword = $nurse->getPassword();

        // Soporta contraseñas hasheadas con password_hash() o en texto plano
        $passwordValid = password_verify($password, $storedPassword) || $storedPassword === $password;

        if (!$passwordValid) {
            return $this->json(['success' => false], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['success' => true]);
    }

  
  #[Route('/nurse', name: 'nurse_create', methods: ['POST'])]
public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
{
    // 400: JSON mal formado
    try {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        return $this->json(['error' => 'Malformed JSON'], Response::HTTP_BAD_REQUEST);
    }

    if (!is_array($data)) {
        return $this->json(['error' => 'Malformed JSON'], Response::HTTP_BAD_REQUEST);
    }

    // 400: campos vacíos o con tipo incorrecto
    foreach (['name', 'email', 'password'] as $field) {
        if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
            return $this->json(
                ['error' => sprintf('Field "%s" is required and must be a non-empty string', $field)],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    $name     = trim($data['name']);
    $email    = strtolower(trim($data['email']));
    $password = $data['password'];

    // 422: validación de negocio
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->json(['error' => 'Invalid email format'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return $this->json(
            ['error' => 'Password must be at least 8 characters and contain letters and numbers'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    try {
        // 409: email ya registrado
        if ($entityManager->getRepository(Nurse::class)->findOneBy(['email' => $email]) !== null) {
            return $this->json(['error' => 'Email already registered'], Response::HTTP_CONFLICT);
        }

        $nurse = new Nurse();
        $nurse->setName($name);
        $nurse->setEmail($email);
        $nurse->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $entityManager->persist($nurse);
        $entityManager->flush();
    } catch (UniqueConstraintViolationException) {
        // Por si dos peticiones llegan a la vez con el mismo email
        return $this->json(['error' => 'Email already registered'], Response::HTTP_CONFLICT);
    } catch (\Throwable) {
        // 500: fallo de BD o error inesperado (sin exponer detalles internos)
        return $this->json(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    // 201: sin devolver la contraseña
    return $this->json([
        'id'    => $nurse->getId(),
        'name'  => $nurse->getName(),
        'email' => $nurse->getEmail(),
    ], Response::HTTP_CREATED);
}
    // Sin "requirements" a propósito: /nurse/abc debe devolver 400 y no 404 del router
    #[Route('/nurse/{id}', name: 'nurse_get_by_id', methods: ['GET'])]
    public function getById(string $id, NurseRepository $nurseRepository): JsonResponse
    {
        $nurseId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($nurseId === false) {
            return $this->json(['error' => 'Invalid id: must be a positive integer'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $nurse = $nurseRepository->find($nurseId);
        } catch (DBALException | ORMException $e) {
            return $this->json(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($nurse === null) {
            return $this->json(['error' => 'Nurse not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $nurse->toArray();
        unset($data['password']);

        return $this->json($data);
    }
}
