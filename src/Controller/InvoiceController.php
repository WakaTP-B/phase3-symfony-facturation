<?php

namespace App\Controller;

use App\Repository\InvoiceRepository;
use App\Entity\Invoice;
use App\Form\InvoiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/invoice')]
final class InvoiceController extends AbstractController
{
    #[Route('/', name: 'app_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoiceRepository, Request $request): Response
    {
        $filter = $request->query->get('status', 'all');

        $invoices = match ($filter) {
            'draft'           => $invoiceRepository->findBy(['status' => 'draft']),
            'pending_payment' => $invoiceRepository->findBy(['status' => 'pending_payment']),
            'paid'            => $invoiceRepository->findBy(['status' => 'paid']),
            default           => $invoiceRepository->findAll(),
        };

        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoices,
            'currentFilter' => $filter,
        ]);
    }

    #[Route('/new', name: 'app_invoice_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        InvoiceRepository $invoiceRepository
    ): Response {
        $invoice = new Invoice();
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Utilisateur connecté
            $invoice->setUser($this->getUser());

            // 2. Date de création
            $invoice->setCreatedAt(new \DateTimeImmutable());

            // 3. Générer le numéro FACT-YYYYMMDD-N
            $today = new \DateTimeImmutable();
            $prefix = 'FACT-' . $today->format('Ymd') . '-';
            $countThisMonth = $invoiceRepository->countByMonth(
                (int) $today->format('Y'),
                (int) $today->format('m')
            );
            $invoice->setNumber($prefix . ($countThisMonth + 1));

            // 4. Calculer le total
            $total = 0;
            foreach ($invoice->getInvoiceItems() as $item) {
                $item->setInvoice($invoice);
                $total += $item->getProduct()->getPrice() * $item->getQuantity();
            }
            $invoice->setTotalTtc((string) $total);

            $em->persist($invoice);
            $em->flush();

            return $this->redirectToRoute('app_invoice_index');
        }

        return $this->render('invoice/new.html.twig', [
            'form' => $form,
        ]);
    }
}
