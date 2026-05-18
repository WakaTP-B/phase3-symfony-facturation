<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Form\InvoiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;

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
        $form = $this->createForm(InvoiceType::class, $invoice, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // User connecté
            $invoice->setUser($this->getUser());

            // Date de création
            $invoice->setCreatedAt(new \DateTimeImmutable());

            // Générer le numéro FACT-YYYYMMDD-N
            $today = new \DateTimeImmutable();
            $prefix = 'FACT-' . $today->format('Ymd') . '-';
            $countThisMonth = $invoiceRepository->countByMonth(
                (int) $today->format('Y'),
                (int) $today->format('m')
            );
            $invoice->setNumber($prefix . ($countThisMonth + 1));

            // Statut
            $saveAs = $request->request->get('save_as', 'draft');
            $invoice->setStatus($saveAs === 'pending' ? 'pending_payment' : 'draft');

            // Lignes depuis inputs cachés Stimulus
            $lines = $request->request->all('invoice_lines') ?? [];
            $total = 0;

            foreach ($lines as $line) {
                $item = new InvoiceItem();
                $item->setInvoice($invoice);
                $item->setQuantity((int) $line['quantity']);
                $item->setName($line['name']);
                $item->setUnitPrice($line['price']);
                $item->setDescription($line['description'] ?? null);

                // Create product in BDD if not exist
                $existingProduct = $productRepository->findOneBy([
                    'name' => $line['name'],
                    'user' => $this->getUser(),
                ]);

                if (!$existingProduct) {
                    $product = new Product();
                    $product->setName($line['name']);
                    $product->setPrice($line['price']);
                    $product->setDescription($line['description'] ?? null);
                    $product->setUnit('piece');
                    $product->setUser($this->getUser());
                    $em->persist($product);
                }

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
            'products' => $productRepository->findBy(['user' => $this->getUser()]),
            'existing_lines' => $request->request->all('invoice_lines') ?? [],

        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(Invoice $invoice, Request $request, EntityManagerInterface $em, ProductRepository $productRepository): Response
    {
        if ($invoice->getStatus() !== 'draft') {
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        $form = $this->createForm(InvoiceType::class, $invoice, [
            'user' => $this->getUser(),
        ]);
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
                $item->setName($line['name']);
                $item->setUnitPrice($line['price']);
                $item->setDescription($line['description'] ?? null);

                // Create product in BDD if not exist
                $existingProduct = $productRepository->findOneBy([
                    'name' => $line['name'],
                    'user' => $this->getUser(),
                ]);

                if (!$existingProduct) {
                    $product = new Product();
                    $product->setName($line['name']);
                    $product->setPrice($line['price']);
                    $product->setDescription($line['description'] ?? null);
                    $product->setUnit('piece');
                    $product->setUser($this->getUser());
                    $em->persist($product);
                }

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
            'products' => $productRepository->findBy(['user' => $this->getUser()]),
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

    #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    public function pdf(Invoice $invoice, GotenbergPdfInterface $gotenberg): Response
    {
        if ($invoice->getStatus() === 'draft') {
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        return $gotenberg->html()
            ->content('invoice/pdf.html.twig', [
                'invoice' => $invoice,
            ])
            ->fileName($invoice->getNumber())
            ->generate()
            ->setDisposition('inline')
            ->stream();
    }

    #[Route('/{id}/send', name: 'app_invoice_send', methods: ['POST'])]
    public function send(Invoice $invoice, GotenbergPdfInterface $gotenberg, MailerInterface $mailer): Response
    {
        if ($invoice->getStatus() === 'draft') {
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        // Create PDF
        ob_start();
        $gotenberg->html()
            ->content('invoice/pdf.html.twig', ['invoice' => $invoice])
            ->fileName($invoice->getNumber())
            ->generate()
            ->process();
        $pdfContent = ob_get_clean();

        // Send email
        $email = (new Email())
            ->from($invoice->getUser()->getEmail())
            ->to($invoice->getClient()->getEmail())
            ->subject('Facture ' . $invoice->getNumber())
            ->text('Veuillez trouver ci-joint votre facture ' . $invoice->getNumber() . '.')
            ->attach($pdfContent, $invoice->getNumber() . '.pdf', 'application/pdf');

        $mailer->send($email);

        $this->addFlash('success', 'Facture envoyée à ' . $invoice->getClient()->getEmail());
        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }
}
