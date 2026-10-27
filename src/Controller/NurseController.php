<?php

namespace App\Controller;

use App\Repository\NurseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    // Sin "requirements" a propósito, igual que getById: /nurse/abc debe devolver 400 y no 405 del router
    #[Route('/nurse/{id}', name: 'nurse_update', methods: ['PUT'])]
    public function update(string $id, Request $request, NurseRepository $nurseRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $nurseId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($nurseId === false) {
            return $this->json(['error' => 'Invalid id: must be a positive integer'], Response::HTTP_BAD_REQUEST);
        }

        // 400: JSON corrupto o vacío
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Malformed JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($data) || $data === []) {
            return $this->json(['error' => 'Request body must be a non-empty JSON object'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $nurse = $nurseRepository->find($nurseId);
        } catch (DBALException | ORMException) {
            return $this->json(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($nurse === null) {
            return $this->json(['error' => 'Nurse not found'], Response::HTTP_NOT_FOUND);
        }

        // 422: PUT reemplaza el recurso, así que name y email son obligatorios; password es opcional
        foreach (['name', 'email'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                return $this->json(
                    ['error' => sprintf('Field "%s" is required and must be a non-empty string', $field)],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $name = trim($data['name']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'] ?? null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($password !== null && (!is_string($password) || strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) {
            return $this->json(
                ['error' => 'Password must be at least 8 characters and contain letters and numbers'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // 204: los datos enviados coinciden con los guardados, no hay nada que actualizar
        if ($name === $nurse->getName() && $email === $nurse->getEmail() && $password === null) {
            return new JsonResponse('', Response::HTTP_NO_CONTENT, [], true);
        }

        try {
            // 409: el email pertenece a otro enfermero
            $owner = $nurseRepository->findOneBy(['email' => $email]);
            // El identity map de Doctrine devuelve la misma instancia si el email es el suyo
            if ($owner !== null && $owner !== $nurse) {
                return $this->json(['error' => 'Email already registered'], Response::HTTP_CONFLICT);
            }

            $nurse->setName($name);
            $nurse->setEmail($email);
            if ($password !== null) {
                $nurse->setPassword(password_hash($password, PASSWORD_BCRYPT));
            }

            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Por si otra petición registra el mismo email a la vez
            return $this->json(['error' => 'Email already registered'], Response::HTTP_CONFLICT);
        } catch (\Throwable) {
            // 500: fallo de Doctrine o de Supabase durante el flush
            return $this->json(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = $nurse->toArray();
        unset($response['password']);

        return $this->json($response);
    }
}
