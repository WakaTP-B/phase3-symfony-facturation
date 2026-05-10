<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route(['/dashboard', '/'], name: 'app_dashboard')]
    public function index(
        InvoiceRepository $invoiceRepository,
        ClientRepository $clientRepository,
        ProductRepository $productRepository
    ): Response {
        $user = $this->getUser();

        $pendingInvoices = $invoiceRepository->findBy(['user' => $user, 'status' => 'pending_payment']);
        $paidInvoices = $invoiceRepository->findBy(['user' => $user, 'status' => 'paid']);

        $ca = array_sum(array_map(fn($i) => (float) $i->getTotalTtc(), $paidInvoices));

        return $this->render('dashboard/index.html.twig', [
            'total_invoices' => count($pendingInvoices),
            'ca' => $ca,
            'total_clients' => count($clientRepository->findAll()),
            'total_products' => count($productRepository->findAll()),
        ]);
    }
}
