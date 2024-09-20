<?php

namespace App\Controller\Billing\Purchase\Invoice;

use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Billing\Purchase\PurchaseSettlement;
use App\Entity\Billing\Sale\SaleInvoice;
use App\Entity\Billing\Sale\SaleSettlement;
use App\Entity\Inventory\Delivery;
use App\Entity\Inventory\DeliveryItem;
use App\Entity\Inventory\DeliveryItemStock;
use App\Entity\Inventory\Reception;
use App\Entity\Inventory\ReceptionItem;
use App\Entity\Inventory\Stock;
use App\Entity\Inventory\StockMovement;
use App\Entity\Partner\CustomerHistory;
use App\Entity\Partner\SupplierHistory;
use App\Entity\Security\User;
use App\Entity\Treasury\BankHistory;
use App\Entity\Treasury\CashDeskHistory;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Purchase\PurchaseSettlementRepository;
use App\Repository\Inventory\LocationRepository;
use App\Repository\Inventory\ReceptionItemRepository;
use App\Repository\Inventory\ReceptionRepository;
use App\Repository\Inventory\StockRepository;
use App\Repository\Partner\SupplierHistoryRepository;
use App\Repository\Setting\Finance\PaymentGatewayRepository;
use App\Repository\Setting\Finance\PaymentMethodRepository;
use App\Repository\Treasury\BankAccountRepository;
use App\Repository\Treasury\BankHistoryRepository;
use App\Repository\Treasury\BankRepository;
use App\Repository\Treasury\CashDeskHistoryRepository;
use App\Repository\Treasury\CashDeskRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class CreatePurchaseInvoiceReceptionSettlementStockInValidateController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                private readonly EntityManagerInterface $manager
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             ReceptionRepository $receptionRepository,
                             ReceptionItemRepository $receptionItemRepository,
                             StockRepository $stockRepository,
                             PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository,
                             PaymentMethodRepository $paymentMethodRepository,
                             CashDeskRepository $cashDeskRepository,
                             LocationRepository $locationRepository,
                             BankRepository $bankRepository,
                             BankHistoryRepository $bankHistoryRepository,
                             PaymentGatewayRepository $paymentGatewayRepository,
                             PurchaseSettlementRepository $purchaseSettlementRepository,
                             CashDeskHistoryRepository $cashDeskHistoryRepository,
                             BankAccountRepository $bankAccountRepository,
                             SupplierHistoryRepository $supplierHistoryRepository,
                             FileUploader $fileUploader,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {
        $id = $request->get('id');

        $invoice = $purchaseInvoiceRepository->find($id);
        if(!($invoice instanceof PurchaseInvoice))
        {
            // Warning
            return new JsonResponse(['hydra:title' => 'This data must be type of invoice.'], 404);
        }

        if(!$invoice)
        {
            // Warning
            return new JsonResponse(['hydra:title' => 'Invoice not found.'], 404);
        }

        $existingReference = $receptionRepository->findOneBy(['otherReference' => $invoice->getInvoiceNumber()]);
        if ($existingReference){
            return new JsonResponse(['hydra:title' => 'This feature already generated.'], 500);
        }

        $uploadedFile = $request->files->get('file');

        if($invoice->getVirtualBalance() <= 0){
            return new JsonResponse(['hydra:title' => 'Amount can not be less or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }
        $amount = $invoice->getVirtualBalance();

        // Payment Method
        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('paymentMethod'));
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);

        if (!$paymentMethod){
            return new JsonResponse(['hydra:description' => 'Payment method not found !'], 404);
        }

        if ($paymentMethod->isIsCashDesk())
        {
            $userCashDesk = $cashDeskRepository->findOneBy(['operator' => $this->getUser(), 'institution' => $this->getUser()->getInstitution()]);
            if (!$userCashDesk)
            {
                return new JsonResponse(['hydra:title' => 'You are not a cashier!'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            if (!$userCashDesk->isIsOpen())
            {
                return new JsonResponse(['hydra:title' => 'You cash desk is not open!'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            if ($invoice->getTtc() > $userCashDesk->getBalance())
            {
                return new JsonResponse(['hydra:title' => 'Amount can not be more than your cash desk balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }
        }
        elseif ($request->get('bankAccount') !== null && $request->get('bankAccount')) {
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bankAccount'));
            $filterId = intval($filter);
            $bankAccount = $bankAccountRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            if (!$bankAccount){
                return new JsonResponse(['hydra:description' => 'Bank account not found !'], 404);
            }

            if ($invoice->getTtc() > $bankAccount->getBalance())
            {
                return new JsonResponse(['hydra:title' => 'Amount can not be more than your bank balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }
        }
        // END: Filter the uri to just take the id and pass it to our object



        // SETTLEMENT SECTION
        $purchaseSettlement = new PurchaseSettlement();

        // set settlement
        $purchaseSettlement->setInvoice($invoice);
        $purchaseSettlement->setSupplier($invoice->getSupplier());

        if ($request->get('reference') !== null){
            $purchaseSettlement->setReference($request->get('reference'));
        }
        else{
            $generateSettlementUniqNumber = $purchaseSettlementRepository->findOneBy([], ['id' => 'DESC']);

            if (!$generateSettlementUniqNumber){
                $uniqueNumber = 'RCPT/SET/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
            }
            else{
                $filterNumber = preg_replace("/[^0-9]/", '', $generateSettlementUniqNumber->getReference());
                $number = intval($filterNumber);

                // Utilisation de number_format() pour ajouter des zéros à gauche
                $uniqueNumber = 'RCPT/SET/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
            }
            $purchaseSettlement->setReference($uniqueNumber);
        }

        $purchaseSettlement->setAmountPay($amount);
        $purchaseSettlement->setAmountRest(0);
        $purchaseSettlement->setSettleAt(new \DateTimeImmutable());

        if ($request->get('bank') !== null && $request->get('bank')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bank'));
            $filterId = intval($filter);
            $bank = $bankRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseSettlement->setBank($bank);
        }
        // cash desk

        $purchaseSettlement->setPaymentMethod($paymentMethod);
        $purchaseSettlement->setNote('settlement from purchase validate');


        $purchaseSettlement->setUser($this->getUser());
        $purchaseSettlement->setYear($this->getUser()->getCurrentYear());
        $purchaseSettlement->setBranch($this->getUser()->getBranch());
        $purchaseSettlement->setInstitution($this->getUser()->getInstitution());

        // upload the file and save its filename
        if ($uploadedFile){
            $purchaseSettlement->setPicture($fileUploader->upload($uploadedFile));
            $purchaseSettlement->setFileName($request->get('fileName'));
            $purchaseSettlement->setFileType($request->get('fileType'));
            $purchaseSettlement->setFileSize($request->get('fileSize'));
        }

        $purchaseSettlement->setStatus('settlement');
        $purchaseSettlement->setIsValidate(true);
        $purchaseSettlement->setValidateAt(new \DateTimeImmutable());
        $purchaseSettlement->setValidateBy($this->getUser());

        // Persist settlement
        $entityManager->persist($purchaseSettlement);

        // Validate Settlement
        if ($paymentMethod->isIsCashDesk())
        {
            // Write cash desk history
            $cashDeskHistory = new CashDeskHistory();
            $cashDeskHistory->setCashDesk($userCashDesk);
            $cashDeskHistory->setReference($purchaseSettlement->getReference());
            $cashDeskHistory->setDescription('Purchase invoice Settlement');
            $cashDeskHistory->setDebit(0);
            $cashDeskHistory->setCredit($amount);
            // balance : en bas
            $cashDeskHistory->setDateAt(new \DateTimeImmutable());

            $cashDeskHistory->setInstitution($this->getUser()->getInstitution());
            $cashDeskHistory->setUser($this->getUser());
            $cashDeskHistory->setYear($this->getUser()->getCurrentYear());
            $entityManager->persist($cashDeskHistory);

            // Update cash desk daily deposit balance
            $userCashDesk->setDailyWithdrawal($userCashDesk->getDailyWithdrawal() + $amount);

            // Update cash desk balance
            $cashDeskHistories = $cashDeskHistoryRepository->findBy(['cashDesk' => $userCashDesk]);

            $debit = 0; $credit = $amount;

            foreach ($cashDeskHistories as $item)
            {
                $debit += $item->getDebit();
                $credit += $item->getCredit();
            }

            $balance = $debit - $credit;

            $cashDeskHistory->setBalance($balance);
            $userCashDesk->setBalance($balance);

            $purchaseSettlement->setCashDesk($userCashDesk);
        }
        elseif ($paymentMethod->isIsBank())
        {
            if ($request->get('bankAccount') !== null && $request->get('bankAccount'))
            {
                // Write bank history
                $bankHistory = new BankHistory();

                $bankHistory->setBankAccount($bankAccount);
                $bankHistory->setReference($purchaseSettlement->getReference());
                $bankHistory->setDescription('Purchase invoice Settlement');
                $bankHistory->setDebit(0);
                $bankHistory->setCredit($amount);
                // balance : en bas
                $bankHistory->setDateAt(new \DateTimeImmutable());

                $bankHistory->setInstitution($this->getUser()->getInstitution());
                $bankHistory->setYear($this->getUser()->getCurrentYear());
                $bankHistory->setUser($this->getUser());
                $entityManager->persist($bankHistory);

                // Update bank balance
                $bankHistories = $bankHistoryRepository->findBy(['bankAccount' => $bankAccount]);

                $debit = 0; $credit = $amount;

                foreach ($bankHistories as $item)
                {
                    $debit += $item->getDebit();
                    $credit += $item->getCredit();
                }

                $balance = $debit - $credit;

                $bankHistory->setBalance($balance);
                $bankAccount->setBalance($balance);

                $purchaseSettlement->setBankAccount($bankAccount);
            }
        }

        // Write supplier history
        $supplierHistory = new SupplierHistory();
        $supplierHistory->setSupplier($purchaseSettlement->getSupplier());
        $supplierHistory->setReference($purchaseSettlement->getReference());

        // Update supplier history balance
        $supplierHistories = $supplierHistoryRepository->findBy(['supplier' => $purchaseSettlement->getSupplier()]);

        $debit = 0; $credit = $amount;

        foreach ($supplierHistories as $item)
        {
            $debit += $item->getDebit();
            $credit += $item->getCredit();
        }

        $balance = $credit - $debit  ;

        $supplierHistory->setBalance($balance);
        $supplierHistory->setCredit(0);
        $supplierHistory->setDebit($amount);
        $supplierHistory->setDescription('purchase settlement ' . $purchaseSettlement->getNote());

        $supplierHistory->setUser($this->getUser());
        $supplierHistory->setInstitution($this->getUser()->getInstitution());
        $supplierHistory->setYear($this->getUser()->getCurrentYear());
        $entityManager->persist($supplierHistory);

        // Update invoice amount paid - balance
        $invoice->setAmountPaid($invoice->getAmountPaid() + $amount);
        $invoice->setBalance($invoice->getTtc() - $invoice->getAmountPaid());
        $invoice->setVirtualBalance(0);

        $invoice->getSupplier()->setDebit($purchaseSettlement->getSupplier()->getDebit() + $amount);

        // SETTLEMENT SECTION END



        // RECEPTION SECTION

        $generateDeliveryUniqNumber = $receptionRepository->findOneBy([], ['id' => 'DESC']);

        if (!$generateDeliveryUniqNumber){
            $uniqueNumber = 'WH/IN/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateDeliveryUniqNumber->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'WH/IN/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $reception = new Reception();
        $reception->setContact($invoice->getSupplier()->getContact());
        // shipping address
        // operation type
        // original document
        $reception->setReference($uniqueNumber);
        $reception->setOtherReference($invoice->getInvoiceNumber());
        // serial number
        $reception->setReceiveAt(new \DateTimeImmutable());

        $reception->setDescription('reception come from purchase validation');
        $reception->setIsValidate(true);
        $reception->setValidateAt(new \DateTimeImmutable());
        $reception->setValidateBy($this->getUser());

        $reception->setStatus('reception');

        $reception->setIsEnable(true);
        $reception->setCreatedAt(new \DateTimeImmutable());
        $reception->setYear($this->getUser()->getCurrentYear());
        $reception->setUser($this->getUser());
        $reception->setBranch($this->getUser()->getBranch());
        $reception->setInstitution($this->getUser()->getInstitution());

        $reception->setPurchaseInvoice($invoice);

        $entityManager->persist($reception);

        $purchaseInvoiceItems = $purchaseInvoiceItemRepository->findBy(['purchaseInvoice' => $invoice]);
        if ($purchaseInvoiceItems){
            foreach ($purchaseInvoiceItems as $purchaseInvoiceItem){
                $receptionItem = new ReceptionItem();
                $receptionItem->setReception($reception);
                $receptionItem->setItem($purchaseInvoiceItem->getItem());
                $receptionItem->setQuantity($purchaseInvoiceItem->getQuantity());

                $receptionItem->setIsEnable(true);
                $receptionItem->setCreatedAt(new \DateTimeImmutable());
                $receptionItem->setYear($this->getUser()->getCurrentYear());
                $receptionItem->setUser($this->getUser());
                $receptionItem->setInstitution($this->getUser()->getInstitution());

                $this->manager->persist($receptionItem);


                // Faire l'entré en stock
                $stockRef = $stockRepository->findOneBy([], ['id' => 'DESC']);
                if (!$stockRef){
                    $reference = 'WH/ST/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
                }
                else{
                    $filterNumber = preg_replace("/[^0-9]/", '', $stockRef->getReference());
                    $number = intval($filterNumber);

                    // Utilisation de number_format() pour ajouter des zéros à gauche
                    $reference = 'WH/ST/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
                }

                $location = $locationRepository->findOneBy(['branch' => $this->getUser()->getBranch()]);

                $stock = new Stock();

                $stock->setReference($reference);

                $stock->setItem($purchaseInvoiceItem->getItem());
                $stock->setQuantity($purchaseInvoiceItem->getQuantity());
                $stock->setAvailableQte($purchaseInvoiceItem->getQuantity());
                $stock->setReserveQte(0);

                $stock->setUnitCost($purchaseInvoiceItem->getItem()->getPrice());
                $stock->setTotalValue($purchaseInvoiceItem->getItem()->getPrice() * $purchaseInvoiceItem->getQuantity());

                $stock->setWarehouse($location->getWarehouse());
                $stock->setLocation($location);

                $stock->setStockAt(new \DateTimeImmutable());

                $stock->setNote('purchase stock in from '.$invoice->getInvoiceNumber());

                $stock->setBranch($this->getUser()->getBranch());
                $stock->setUser($this->getUser());

                $stock->setInstitution($this->getUser()->getInstitution());
                $stock->setYear($this->getUser()->getCurrentYear());

                $entityManager->persist($stock);


                // Stock movement
                $stockMovement = new StockMovement();
                $stockMovement->setReference($stock->getReference());
                $stockMovement->setItem($purchaseInvoiceItem->getItem());
                $stockMovement->setQuantity($purchaseInvoiceItem->getQuantity());
                $stockMovement->setUnitCost($stock->getUnitCost());
                $stockMovement->setFromWarehouse($stock->getWarehouse());
                // from location
                // to warehouse
                // to location
                $stockMovement->setStockAt(new \DateTimeImmutable());
                $stockMovement->setLoseAt($stock->getLoseAt());
                $stockMovement->setNote('purchase stock in');
                $stockMovement->setIsOut(true);

                $stockMovement->setStock($stock);

                $stockMovement->setYear($this->getUser()->getCurrentYear());
                $stockMovement->setUser($this->getUser());
                $stockMovement->setCreatedAt(new \DateTimeImmutable());
                $stockMovement->setIsEnable(true);
                $stockMovement->setUpdatedAt(new \DateTimeImmutable());
                $stockMovement->setInstitution($this->getUser()->getInstitution());

                $entityManager->persist($stockMovement);

            }
        }

        // Reception SECTION END

        // other invoice status update
        $invoice->setOtherStatus('reception');

        $this->manager->flush();

        return $this->json(['hydra:member' => $reception]);
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
