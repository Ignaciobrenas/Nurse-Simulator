<?php

namespace App\Controller;

use App\Repository\NurseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
