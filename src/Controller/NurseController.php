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
}
