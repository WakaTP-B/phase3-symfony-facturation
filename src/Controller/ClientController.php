<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/client')]
final class ClientController extends AbstractController
{
    #[Route(name: 'app_client_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ClientRepository $clientRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $editId = $request->query->getInt('edit');
        $showNewForm = $request->query->getBoolean('new');
        $form = null;
        $editingClient = null;

        if ($editId) {
            // Edit
            $editingClient = $clientRepository->find($editId);
            if (!$editingClient) {
                throw $this->createNotFoundException('Client introuvable');
            }
            $form = $this->createForm(ClientType::class, $editingClient);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->flush();
                return $this->redirectToRoute('app_client_index', ['edit' => $editId]);
            }
        } elseif ($showNewForm) {
            // Create
            $client = new Client();
            $form = $this->createForm(ClientType::class, $client);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($client);
                $entityManager->flush();
                return $this->redirectToRoute('app_client_index', ['new' => 1]);
            }
        }

        return $this->render('client/index.html.twig', [
            'clients' => $clientRepository->findAll(),
            'form' => $form,
            'showForm' => $showNewForm || $editId,
            'editingClient' => $editingClient,
        ]);
    }


    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }
}
