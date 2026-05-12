<?php

namespace App\Controller;

use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Product;
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
    public function new(Request $request, EntityManagerInterface $em, InvoiceRepository $invoiceRepository, ProductRepository $productRepository): Response
    {
        $invoice = new Invoice();
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // User connecté
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

            // 4. Statut
            $saveAs = $request->request->get('save_as', 'draft');
            $invoice->setStatus($saveAs === 'pending' ? 'pending_payment' : 'draft');

            // 5. Lignes depuis inputs cachés Stimulus
            $lines = $request->request->all('invoice_lines') ?? [];
            $total = 0;

            foreach ($lines as $line) {
                $item = new InvoiceItem();
                $item->setInvoice($invoice);
                $item->setQuantity((int) $line['quantity']);

                // Check if exist
                $product = $productRepository->findOneBy([
                    'name' => $line['name'],
                    'price' => $line['price']
                ]);

                if (!$product) {
                    $product = new Product();
                    $product->setName($line['name']);
                    $product->setPrice($line['price']);
                    $product->setDescription('');
                    $product->setUnit('piece');
                    $em->persist($product);
                }

                $item->setProduct($product);
                $invoice->addInvoiceItem($item);
                $total += $line['price'] * $line['quantity'];
                $em->persist($item);
            }

            $invoice->setTotalTtc((string) $total);

            $em->persist($invoice);
            $em->flush();

            return $this->redirectToRoute('app_invoice_index');
        }

        return $this->render('invoice/new.html.twig', [
            'form' => $form,
            'products' => $productRepository->findAll(),
            'existing_lines' => $request->request->all('invoice_lines') ?? [],

        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(Invoice $invoice, Request $request, EntityManagerInterface $em, ProductRepository $productRepository): Response
    {
        if ($invoice->getStatus() !== 'draft') {
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            foreach ($invoice->getInvoiceItems() as $item) {
                $em->remove($item);
            }
            $invoice->getInvoiceItems()->clear();

            $saveAs = $request->request->get('save_as', 'draft');
            $invoice->setStatus($saveAs === 'pending' ? 'pending_payment' : 'draft');

            $lines = $request->request->all('invoice_lines') ?? [];
            $total = 0;

            foreach ($lines as $line) {
                $item = new InvoiceItem();
                $item->setInvoice($invoice);
                $item->setQuantity((int) $line['quantity']);

                // Check if exist
                $product = $productRepository->findOneBy([
                    'name' => $line['name'],
                    'price' => $line['price']
                ]);

                if (!$product) {
                    $product = new Product();
                    $product->setName($line['name']);
                    $product->setPrice($line['price']);
                    $product->setDescription('');
                    $product->setUnit('piece');
                    $em->persist($product);
                }

                $item->setProduct($product);
                $invoice->addInvoiceItem($item);
                $total += $line['price'] * $line['quantity'];
                $em->persist($item);
            }

            $invoice->setTotalTtc((string) $total);
            $em->flush();

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('invoice/edit.html.twig', [
            'form' => $form,
            'invoice' => $invoice,
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(Invoice $invoice, EntityManagerInterface $em, Request $request): Response
    {
        if ($invoice->getStatus() === 'draft') {
            if ($this->isCsrfTokenValid('delete' . $invoice->getId(), $request->getPayload()->getString('_token'))) {
                $em->remove($invoice);
                $em->flush();
            }
        }
        return $this->redirectToRoute('app_invoice_index');
    }

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/validate', name: 'app_invoice_validate', methods: ['POST'])]
    public function validate(Invoice $invoice, EntityManagerInterface $em): Response
    {
        if ($invoice->getStatus() === 'draft') {
            $invoice->setStatus('pending_payment');
            $em->flush();
        }
        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/pay', name: 'app_invoice_pay', methods: ['POST'])]
    public function pay(Invoice $invoice, EntityManagerInterface $em): Response
    {
        if ($invoice->getStatus() === 'pending_payment') {
            $invoice->setStatus('paid');
            $em->flush();
        }
        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }
}
