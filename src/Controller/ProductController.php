<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $editId = $request->query->getInt('edit');
        $showNewForm = $request->query->getBoolean('new');
        $form = null;
        $editingProduct = null;

        if ($editId) {
            // Edit
            $editingProduct = $productRepository->find($editId);
            if (!$editingProduct) {
                throw $this->createNotFoundException('Produit introuvable');
            }
            $form = $this->createForm(ProductType::class, $editingProduct);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->flush();
                return $this->redirectToRoute('app_product_index', ['edit' => $editId]);
            }
            
        } elseif ($showNewForm) {
            // Create
            $product = new Product();
            $form = $this->createForm(ProductType::class, $product);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($product);
                $entityManager->flush();
                return $this->redirectToRoute('app_product_index', ['new' => 1]);
            }
        }

        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findAll(),
            'form' => $form,
            'showForm' => $showNewForm || $editId,
            'editingProduct' => $editingProduct,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
