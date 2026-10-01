<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
}
