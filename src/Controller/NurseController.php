<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NurseController extends AbstractController
{
    #[Route('/nurse/name/{name}', name: 'nurse_find_by_name', methods: ['GET'])]
    public function findByName(string $name): JsonResponse
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/data/nurses.json';
        $nurses = json_decode(file_get_contents($filePath), true) ?? [];

        foreach ($nurses as $nurse) {
            if (strcasecmp($nurse['name'], $name) === 0) {
                return $this->json($nurse);
            }
        }

        return $this->json(['error' => 'Nurse not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/nurse/index', name: 'nurse_index', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $file = $this->getParameter('kernel.project_dir') . '/data/nurses.json';

        if (!is_file($file)) {
            return $this->json(['error' => 'nurses.json not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $nurses = json_decode(file_get_contents($file), true);

        if (!is_array($nurses)) {
            return $this->json(['error' => 'nurses.json is not valid'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($nurses);
    }

    #[Route('/nurse/login', name: 'nurse_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return $this->json(['success' => false, 'message' => 'Username and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/data/nurses.json';
        $nurses = json_decode(file_get_contents($filePath), true) ?? [];

        foreach ($nurses as $nurse) {
            if (
                (strcasecmp($nurse['email'] ?? '', $username) === 0 || strcasecmp($nurse['name'] ?? '', $username) === 0)
                && $nurse['password'] === $password
            ) {
                return $this->json(['success' => true]);
            }
        }

        return $this->json(['success' => false], Response::HTTP_UNAUTHORIZED);
    }
}
