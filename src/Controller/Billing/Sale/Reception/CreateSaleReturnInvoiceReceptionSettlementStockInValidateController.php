<?php

namespace App\Controller\Billing\Sale\Reception;

use App\Entity\Billing\Sale\SaleReturnInvoice;
use App\Entity\Billing\Sale\SaleSettlement;
use App\Entity\Inventory\Reception;
use App\Entity\Inventory\ReceptionItem;
use App\Entity\Inventory\ReceptionItemStock;
use App\Entity\Inventory\StockMovement;
use App\Entity\Partner\CustomerHistory;
use App\Entity\Security\User;
use App\Entity\Treasury\BankHistory;
use App\Entity\Treasury\CashDeskHistory;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemStockRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
use App\Repository\Inventory\ReceptionItemRepository;
use App\Repository\Inventory\ReceptionRepository;
use App\Repository\Inventory\StockMovementRepository;
use App\Repository\Inventory\StockRepository;
use App\Repository\Partner\CustomerHistoryRepository;
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
class CreateSaleReturnInvoiceReceptionSettlementStockInValidateController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage, private readonly EntityManagerInterface $manager)
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             ReceptionRepository $receptionRepository,
                             ReceptionItemRepository $receptionItemRepository,
                             StockRepository $stockRepository,
                             SaleReturnInvoiceItemStockRepository $saleReturnInvoiceItemStockRepository,
                             PaymentMethodRepository $paymentMethodRepository,
                             CashDeskRepository $cashDeskRepository,
                             BankRepository $bankRepository,
                             BankHistoryRepository $bankHistoryRepository,
                             PaymentGatewayRepository $paymentGatewayRepository,
                             SaleSettlementRepository $saleSettlementRepository,
                             CashDeskHistoryRepository $cashDeskHistoryRepository,
                             BankAccountRepository $bankAccountRepository,
                             CustomerHistoryRepository $customerHistoryRepository,
                             StockMovementRepository $stockMovementRepository,
                             FileUploader $fileUploader,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {
        $id = $request->get('id');

        $saleReturnInvoice = $saleReturnInvoiceRepository->find($id);
        if(!($saleReturnInvoice instanceof SaleReturnInvoice))
        {
            return new JsonResponse(['hydra:title' => 'This data must be type of return invoice.'], 404);
        }

        $existingReference = $receptionRepository->findOneBy(['otherReference' => $saleReturnInvoice->getInvoiceNumber()]);
        if ($existingReference){
            return new JsonResponse(['hydra:title' => 'This sale return invoice already has a reception.'], 500);
        }

        $uploadedFile = $request->files->get('file');

        if($saleReturnInvoice->getVirtualBalance() <= 0){
            return new JsonResponse(['hydra:title' => 'Amount can not be less or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }
        $amount = $saleReturnInvoice->getVirtualBalance();

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
                return new JsonResponse(['hydra:title' => 'Your cash desk is not open!'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            if($amount > $userCashDesk->getBalance())
            {
                return new JsonResponse(['hydra:title' => 'Insufficient balance for this operation'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
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

            if($amount > $bankAccount->getBalance()){
                return new JsonResponse(['hydra:title' => 'Insufficient balance for this operation'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }
        }
        // END: Filter the uri to just take the id and pass it to our object



        // SETTLEMENT SECTION
        $saleSettlement = new SaleSettlement();

        // set settlement
        $saleSettlement->setSaleReturnInvoice($saleReturnInvoice);
        $saleSettlement->setCustomer($saleReturnInvoice->getCustomer());

        if ($request->get('reference') !== null){
            $saleSettlement->setReference($request->get('reference'));
        }
        else{
            $generateSettlementUniqNumber = $saleSettlementRepository->findOneBy(['branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
            if (!$generateSettlementUniqNumber){
                $uniqueNumber = 'SAL/SET/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
            }
            else{
                $filterNumber = preg_replace("/[^0-9]/", '', $generateSettlementUniqNumber->getReference());
                $number = intval($filterNumber);

                // Utilisation de number_format() pour ajouter des zéros à gauche
                $uniqueNumber = 'SAL/SET/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
            }
            $saleSettlement->setReference($uniqueNumber);
        }

        $saleSettlement->setAmountPay($amount);
        $saleSettlement->setAmountRest(0);
        $saleSettlement->setSettleAt(new \DateTimeImmutable());

        if ($request->get('bank') !== null && $request->get('bank')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bank'));
            $filterId = intval($filter);
            $bank = $bankRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $saleSettlement->setBank($bank);
        }

        $saleSettlement->setNote('sale return invoice settlement validate');
        $saleSettlement->setPaymentMethod($paymentMethod);

        $saleSettlement->setStatus('settlement');
        $saleSettlement->setIsValidate(true);
        $saleSettlement->setValidateAt(new \DateTimeImmutable());
        $saleSettlement->setValidateBy($this->getUser());

        $saleSettlement->setUser($this->getUser());
        $saleSettlement->setYear($this->getUser()->getCurrentYear());
        $saleSettlement->setBranch($this->getUser()->getBranch());
        $saleSettlement->setInstitution($this->getUser()->getInstitution());

        // upload the file and save its filename
        if ($uploadedFile){
            $saleSettlement->setPicture($fileUploader->upload($uploadedFile));
            $saleSettlement->setFileName($request->get('fileName'));
            $saleSettlement->setFileType($request->get('fileType'));
            $saleSettlement->setFileSize($request->get('fileSize'));
        }

        // Persist settlement
        $entityManager->persist($saleSettlement);

        // Validate Settlement
        if ($paymentMethod->isIsCashDesk())
        {
            // Write cash desk history
            $cashDeskHistory = new CashDeskHistory();

            $cashDeskHistoryRef = $cashDeskHistoryRepository->findOneBy(['branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
            if (!$cashDeskHistoryRef){
                $reference = 'CASH/HIS/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
            }
            else{
                $filterNumber = preg_replace("/[^0-9]/", '', $cashDeskHistoryRef->getReference());
                $number = intval($filterNumber);

                // Utilisation de number_format() pour ajouter des zéros à gauche
                $reference = 'CASH/HIS/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
            }

            $cashDeskHistory->setCashDesk($userCashDesk);
            $cashDeskHistory->setReference($reference);
            $cashDeskHistory->setDescription('sale return invoice settlement cash history');
            $cashDeskHistory->setDebit(0);
            $cashDeskHistory->setCredit($amount);
            // balance : en bas
            $cashDeskHistory->setDateAt(new \DateTimeImmutable());

            $cashDeskHistory->setBranch($this->getUser()->getBranch());
            $cashDeskHistory->setInstitution($this->getUser()->getInstitution());
            $cashDeskHistory->setUser($this->getUser());
            $cashDeskHistory->setYear($this->getUser()->getCurrentYear());
            $entityManager->persist($cashDeskHistory);

            // Update cash desk daily withdrawal balance
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

            $saleSettlement->setCashDesk($userCashDesk);
        }
        elseif ($paymentMethod->isIsBank())
        {
            if ($request->get('bankAccount') !== null && $request->get('bankAccount'))
            {
                // Write bank history
                $bankHistory = new BankHistory();

                $bankHistoryRef = $bankHistoryRepository->findOneBy(['branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
                if (!$bankHistoryRef){
                    $reference = 'BNK/HIS/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
                }
                else{
                    $filterNumber = preg_replace("/[^0-9]/", '', $bankHistoryRef->getReference());
                    $number = intval($filterNumber);

                    // Utilisation de number_format() pour ajouter des zéros à gauche
                    $reference = 'BNK/HIS/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
                }

                $bankHistory->setBankAccount($bankAccount);
                $bankHistory->setReference($reference);
                $bankHistory->setDescription('sale return invoice settlement bank account history');
                $bankHistory->setDebit(0);
                $bankHistory->setCredit($amount);
                // balance : en bas
                $bankHistory->setDateAt(new \DateTimeImmutable());

                $bankHistory->setBranch($this->getUser()->getBranch());
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

                $saleSettlement->setBankAccount($bankAccount);
            }
        }

        // Write customer history
        $customerHistory = new CustomerHistory();

        $customerHistoryRef = $customerHistoryRepository->findOneBy(['branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
        if (!$customerHistoryRef){
            $reference = 'CLT/HIS/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $customerHistoryRef->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $reference = 'CLT/HIS/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $customerHistory->setCustomer($saleSettlement->getCustomer());
        $customerHistory->setReference($reference);
        $customerHistory->setDescription('sale return invoice settlement customer history');
        $customerHistory->setDebit($amount);
        $customerHistory->setCredit(0);

        // Update customer history balance
        $customerHistories = $customerHistoryRepository->findBy(['customer' => $saleSettlement->getCustomer()]);

        $debit = $amount; $credit = 0;

        foreach ($customerHistories as $item)
        {
            $debit += $item->getDebit();
            $credit += $item->getCredit();
        }

        $balance = $debit - $credit;

        $customerHistory->setBalance($balance);

        $customerHistory->setUser($this->getUser());
        $customerHistory->setBranch($this->getUser()->getBranch());
        $customerHistory->setInstitution($this->getUser()->getInstitution());
        $customerHistory->setYear($this->getUser()->getCurrentYear());
        $entityManager->persist($customerHistory);

        // Update invoice amount paid - balance
        $saleReturnInvoice->setAmountPaid($amount);
        $saleReturnInvoice->setBalance(0);
        $saleReturnInvoice->setVirtualBalance(0);

        $saleReturnInvoice->getCustomer()->setDebit($saleSettlement->getCustomer()->getDebit() + $amount);

        // SETTLEMENT SECTION END



        // RECEPTION SECTION

        $generateReceptionUniqNumber = $receptionRepository->findOneBy(['branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
        if (!$generateReceptionUniqNumber){
            $uniqueNumber = 'SAL/RET/REC/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateReceptionUniqNumber->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'SAL/RET/REC/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $reception = new Reception();

        $reception->setSaleReturnInvoice($saleReturnInvoice);
        $reception->setContact($saleReturnInvoice->getCustomer()->getContact());
        // shipping address
        // operation type
        // original document
        $reception->setReference($uniqueNumber);
        $reception->setOtherReference($saleReturnInvoice->getInvoiceNumber());
        // serial number
        $reception->setReceiveAt(new \DateTimeImmutable());
        $reception->setDescription('sale return invoice reception validate');
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

        $entityManager->persist($reception);

        $saleReturnInvoiceItems = $saleReturnInvoiceItemRepository->findBy(['saleReturnInvoice' => $saleReturnInvoice]);
        if ($saleReturnInvoiceItems)
        {
            foreach ($saleReturnInvoiceItems as $saleReturnInvoiceItem)
            {
                $receptionItem = new ReceptionItem();

                $receptionItem->setReception($reception);
                $receptionItem->setItem($saleReturnInvoiceItem->getItem());
                $receptionItem->setQuantity($saleReturnInvoiceItem->getQuantity());

                $receptionItem->setIsEnable(true);
                $receptionItem->setCreatedAt(new \DateTimeImmutable());
                $receptionItem->setYear($this->getUser()->getCurrentYear());
                $receptionItem->setUser($this->getUser());
                $receptionItem->setInstitution($this->getUser()->getInstitution());

                $this->manager->persist($receptionItem);

                // Faire l'entrée en stock
                $saleReturnInvoiceItemStocks = $saleReturnInvoiceItemStockRepository->findBy(['saleReturnInvoiceItem' => $saleReturnInvoiceItem]);

                if ($saleReturnInvoiceItemStocks)
                {
                    foreach ($saleReturnInvoiceItemStocks as $saleReturnInvoiceItemStock)
                    {
                        // create reception item stock
                        $receptionItemStock = new ReceptionItemStock();

                        $receptionItemStock->setSaleReturnInvoiceItem($saleReturnInvoiceItem);
                        $receptionItemStock->setReceptionItem($receptionItem);
                        $receptionItemStock->setStock($saleReturnInvoiceItemStock->getStock());
                        $receptionItemStock->setQuantity($saleReturnInvoiceItemStock->getQuantity());

                        $receptionItemStock->setCreatedAt(new \DateTimeImmutable());
                        $receptionItemStock->setUser($this->getUser());
                        $receptionItemStock->setYear($this->getUser()->getCurrentYear());
                        $receptionItemStock->setBranch($this->getUser()->getBranch());
                        $receptionItemStock->setInstitution($this->getUser()->getInstitution());

                        $this->manager->persist($receptionItemStock);
                        // create reception item stock end


                        $stock = $saleReturnInvoiceItemStock->getStock();

                        $stock->setAvailableQte($stock->getAvailableQte() + $saleReturnInvoiceItemStock->getQuantity());
                        $stock->setQuantity($stock->getQuantity() + $saleReturnInvoiceItemStock->getQuantity());

                        // Stock movement
                        $stockMovement = new StockMovement();

                        $stockOutRef = $stockMovementRepository->findOneBy(['isOut' => false, 'branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
                        if (!$stockOutRef){
                            $reference = 'WH/IN/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
                        }
                        else{
                            $filterNumber = preg_replace("/[^0-9]/", '', $stockOutRef->getReference());
                            $number = intval($filterNumber);

                            // Utilisation de number_format() pour ajouter des zéros à gauche
                            $reference = 'WH/IN/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
                        }

                        $stockMovement->setReference($stock->getReference());
                        $stockMovement->setItem($stock->getItem());
                        $stockMovement->setQuantity($saleReturnInvoiceItemStock->getQuantity());
                        $stockMovement->setUnitCost($stock->getUnitCost());
                        $stockMovement->setToWarehouse($stock->getWarehouse());
                        // from location
                        // from warehouse
                        // from location
                        $stockMovement->setStockAt(new \DateTimeImmutable());
                        $stockMovement->setLoseAt($stock->getLoseAt());
                        $stockMovement->setNote('sale return invoice complete stock in');
                        $stockMovement->setType('sale return invoice complete stock in');
                        $stockMovement->setIsOut(false);
                        $stockMovement->setStock($stock);

                        $stockMovement->setYear($this->getUser()->getCurrentYear());
                        $stockMovement->setUser($this->getUser());
                        $stockMovement->setCreatedAt(new \DateTimeImmutable());
                        $stockMovement->setIsEnable(true);
                        $stockMovement->setUpdatedAt(new \DateTimeImmutable());
                        $stockMovement->setBranch($this->getUser()->getBranch());
                        $stockMovement->setInstitution($this->getUser()->getInstitution());

                        $entityManager->persist($stockMovement);
                    }
                }

            }
        }

        // DELIVERY SECTION END

        // other invoice status update
        $saleReturnInvoice->setOtherStatus('reception');

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
