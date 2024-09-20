<?php
namespace App\State\Processor\Billing\Purchase;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Billing\Sale\SaleInvoice;
use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Partner\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PutPurchaseInvoiceProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly Request $request,
                                private readonly EntityManagerInterface $manager,
                                private readonly SupplierRepository $supplierRepository,
                                private readonly PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                                Private readonly PurchaseInvoiceRepository $purchaseInvoiceRepository) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {

        $invoiceData = json_decode($this->request->getContent(), true);

        if(!$data instanceof PurchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of invoice.'], 404);
        }

        $purchaseInvoice = $this->purchaseInvoiceRepository->find($data->getId());
        if(!$purchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'Invoice not found.'], 404);
        }

        if (isset($invoiceData['invoiceAt'])){
            $purchaseInvoice->setInvoiceAt(new \DateTimeImmutable($invoiceData['invoiceAt']));
        }

        if (isset($invoiceData['deadLine'])){
            $purchaseInvoice->setDeadLine(new \DateTimeImmutable($invoiceData['deadLine']));
        }

        if (isset($invoiceData['paymentReference'])){
            $purchaseInvoice->setPaymentReference($invoiceData['paymentReference']);
        }

        if (isset($invoiceData['supplier'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $invoiceData['supplier']);
            $filterId = intval($filter);
            $supplier = $this->supplierRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseInvoice->setSupplier($supplier);
        }else{
            $defaultClient = $this->supplierRepository->findOneBy(['code' => 'DIVERS']);
            $purchaseInvoice->setSupplier($defaultClient);
        }

        $amount = $this->purchaseInvoiceItemRepository->purchaseInvoiceHtAmount($purchaseInvoice)[0][1];
        $purchaseInvoice->setAmount($amount);

        $taxResult = 0;
        $discountAmount = 0;

        $purchaseInvoiceItems = $this->purchaseInvoiceItemRepository->findBy(['purchaseInvoice' => $purchaseInvoice]);
        foreach ($purchaseInvoiceItems as $purchaseInvoiceItem){
            if ($purchaseInvoiceItem->getTaxes()){
                foreach ($purchaseInvoiceItem->getTaxes() as $tax){
                    $taxResult = $taxResult + $purchaseInvoiceItem->getAmount() * $tax->getRate() / 100;
                }
            }

            $purchaseInvoiceItem->setSupplier($purchaseInvoice->getSupplier());
            $purchaseInvoiceItem->setYear($purchaseInvoiceItem->getYear());

            $discountAmount = $discountAmount + $purchaseInvoiceItem->getAmount() * $purchaseInvoiceItem->getDiscount() / 100;
        }

        $amountTtc = $this->purchaseInvoiceItemRepository->purchaseInvoiceHtAmount($purchaseInvoice)[0][1] + $taxResult - $discountAmount;

        $purchaseInvoice->setTtc($amountTtc);
        $purchaseInvoice->setBalance($amountTtc);
        $purchaseInvoice->setVirtualBalance($amountTtc);

        $this->manager->flush();

        return $this->processor->process($purchaseInvoice, $operation, $uriVariables, $context);

    }

    public function getIdFromApiResourceId(string $apiId){
        $lastIndexOf = strrpos($apiId, '/');
        $id = substr($apiId, $lastIndexOf+1);
        return intval($id);
    }

    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }
}
