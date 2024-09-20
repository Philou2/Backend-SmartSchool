<?php

namespace App\Controller\Billing\Purchase\Invoice;

use App\Entity\Billing\Purchase\PurchaseSettlement;
use App\Entity\Partner\SupplierHistory;
use App\Entity\Security\User;
use App\Entity\Treasury\BankHistory;
use App\Entity\Treasury\CashDeskHistory;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Purchase\PurchaseSettlementRepository;
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
class CreatePurchaseInvoiceSettlementValidateController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             PurchaseSettlementRepository $purchaseSettlementRepository,
                             EntityManagerInterface $entityManager,
                             CashDeskRepository $cashDeskRepository,
                             CashDeskHistoryRepository $cashDeskHistoryRepository,
                             SupplierHistoryRepository $supplierHistoryRepository,
                             PaymentMethodRepository $paymentMethodRepository,
                             BankHistoryRepository $bankHistoryRepository,
                             BankAccountRepository $bankAccountRepository,
                             BankRepository $bankRepository,
                             FileUploader $fileUploader,
                             PaymentGatewayRepository $paymentGatewayRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $uploadedFile = $request->files->get('file');

        $invoice = $purchaseInvoiceRepository->find($id);
        if (!$invoice){
            return new JsonResponse(['hydra:description' => 'This invoice is not found.'], 404);
        }

        $purchaseSettlement = new PurchaseSettlement();

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('paymentMethod'));
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);
        //dd($paymentMethod);

        if (!$paymentMethod){
            return new JsonResponse(['hydra:description' => 'Payment method not found.'], 404);
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

            $purchaseSettlement->setCashDesk($userCashDesk);

        }

        if ($paymentMethod->isIsBank())
        {
            if ($request->get('bankAccount') !== null && $request->get('bankAccount')) {
                // START: Filter the uri to just take the id and pass it to our object
                $filter = preg_replace("/[^0-9]/", '', $request->get('bankAccount'));
                $filterId = intval($filter);
                $bankAccount = $bankAccountRepository->find($filterId);
                // END: Filter the uri to just take the id and pass it to our object

                if (!$bankAccount){
                    return new JsonResponse(['hydra:description' => 'Bank account not found !'], 404);
                }

                $purchaseSettlement->setBankAccount($bankAccount);

                if ($request->get('bank') !== null && $request->get('bank')){
                    // START: Filter the uri to just take the id and pass it to our object
                    $filter = preg_replace("/[^0-9]/", '', $request->get('bank'));
                    $filterId = intval($filter);
                    $bank = $bankRepository->find($filterId);
                    // END: Filter the uri to just take the id and pass it to our object

                    $purchaseSettlement->setBank($bank);
                }
            }
        }
        // END: Filter the uri to just take the id and pass it to our object

        /*if ($request->get('paymentGateway') !== null && $request->get('paymentGateway')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('paymentGateway'));
            $filterId = intval($filter);
            $paymentGateway = $paymentGatewayRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseSettlement->setPaymentGateway($paymentGateway);
        }*/

        if($request->get('amountPay') <= 0 ){
            return new JsonResponse(['hydra:title' => 'Amount can not be less or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if ($request->get('amountPay') > $invoice->getVirtualBalance())
        {
            return new JsonResponse(['hydra:title' => 'Amount can not be more than balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if (!$invoice->getSupplier())
        {
            return new JsonResponse(['hydra:title' => 'Supplier not found!'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }


        // CREATE SETTLEMENT SECTION

        $purchaseSettlement->setInvoice($invoice);
        $purchaseSettlement->setSupplier($invoice->getSupplier());

        $generateSettlementUniqNumber = $purchaseSettlementRepository->findOneBy([], ['id' => 'DESC']);

        if (!$generateSettlementUniqNumber){
            $uniqueNumber = 'PUR/SET/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateSettlementUniqNumber->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'PUR/SET/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $purchaseSettlement->setReference($uniqueNumber);

        $purchaseSettlement->setAmountPay($request->get('amountPay'));
        $purchaseSettlement->setAmountRest($invoice->getVirtualBalance() - $request->get('amountPay'));

        $purchaseSettlement->setSettleAt(new \DateTimeImmutable($request->get('settleAt')));

        $purchaseSettlement->setPaymentMethod($paymentMethod);
        $purchaseSettlement->setNote('purchase invoice settlement validate');

        $purchaseSettlement->setUser($this->getUser());
        $purchaseSettlement->setYear($this->getUser()->getCurrentYear());
        $purchaseSettlement->setBranch($this->getUser()->getBranch());
        $purchaseSettlement->setInstitution($this->getUser()->getInstitution());

        $purchaseSettlement->setIsValidate(true);
        $purchaseSettlement->setValidateAt(new \DateTimeImmutable());
        $purchaseSettlement->setValidateBy($this->getUser());

        // upload the file and save its filename
        if ($uploadedFile){
            $purchaseSettlement->setPicture($fileUploader->upload($uploadedFile));
            $purchaseSettlement->setFileName($request->get('fileName'));
            $purchaseSettlement->setFileType($request->get('fileType'));
            $purchaseSettlement->setFileSize($request->get('fileSize'));
        }

        // Persist settlement
        $entityManager->persist($purchaseSettlement);


        // VALIDATE SETTLEMENT SECTION

        $amount = $request->get('amountPay');

        if ($paymentMethod->isIsCashDesk())
        {
            $userCashDesk = $cashDeskRepository->findOneBy(['operator' => $this->getUser(), 'institution' => $this->getUser()->getInstitution()]);
            if (!$userCashDesk)
            {
                return new JsonResponse(['hydra:title' => 'user_is_not_cash_desk'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            if ($request->get('amountPay') > $userCashDesk->getBalance())
            {
                return new JsonResponse(['hydra:title' => 'Amount can not be more than you cash desk balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            // Write cash desk history
            $cashDeskHistory = new CashDeskHistory();
            $cashDeskHistory->setCashDesk($userCashDesk);
            $cashDeskHistory->setReference($purchaseSettlement->getReference());
            $cashDeskHistory->setDescription('purchase invoice settlement validate');
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

            $entityManager->flush();
        }
        elseif ($paymentMethod->isIsBank())
        {
            if ($request->get('bankAccount') !== null && $request->get('bankAccount'))
            {
                // Write bank history
                $bankHistory = new BankHistory();

                // START: Filter the uri to just take the id and pass it to our object
                $filter = preg_replace("/[^0-9]/", '', $request->get('bankAccount'));
                $filterId = intval($filter);
                $bankAccount = $bankAccountRepository->find($filterId);
                // END: Filter the uri to just take the id and pass it to our object

                if ($request->get('amountPay') > $bankAccount->getBalance())
                {
                    return new JsonResponse(['hydra:title' => 'Amount can not be more than you account balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
                }

                $bankHistory->setBankAccount($bankAccount);
                $bankHistory->setReference($purchaseSettlement->getReference());
                $bankHistory->setDescription('purchase invoice settlement validate');
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
                $entityManager->flush();
            }
        }

        $supplierHistory = new SupplierHistory();
        $supplierHistory->setSupplier($purchaseSettlement->getSupplier());
        $supplierHistory->setReference($purchaseSettlement->getReference());
        $supplierHistory->setUser($this->getUser());
        $supplierHistory->setInstitution($this->getUser()->getInstitution());
        $supplierHistory->setYear($this->getUser()->getCurrentYear());

        // Update supplier history balance
        $supplierHistories = $supplierHistoryRepository->findBy(['supplier' => $purchaseSettlement->getSupplier()]);

        $debit = $amount; $credit = 0;

        foreach ($supplierHistories as $item)
        {
            $debit += $item->getDebit();
            $credit += $item->getCredit();
        }

        $balance = $credit - $debit  ;

        $supplierHistory->setBalance($balance);
        $supplierHistory->setCredit(0);
        $supplierHistory->setDebit($amount);
        $supplierHistory->setDescription('purchase invoice settlement validate');
        $entityManager->persist($supplierHistory);

        $purchaseSettlement->getSupplier()->setDebit($purchaseSettlement->getSupplier()->getDebit() + $amount);

        // Update invoice
        $invoice?->setAmountPaid($purchaseSettlement->getInvoice()->getAmountPaid() + $purchaseSettlement->getAmountPay());
        $invoice?->setBalance($purchaseSettlement->getInvoice()->getTtc() - $purchaseSettlement->getInvoice()->getAmountPaid());
        $invoice->setVirtualBalance($invoice->getVirtualBalance() - $request->get('amountPay'));


        // Verifier si le montant a regler est inferieur au panier
        $settlementAmount = $amount;

        // $purchaseInvoiceItems form $purchaseInvoice;
        $purchaseInvoiceItems = $purchaseInvoiceItemRepository->findPurchaseInvoiceItemByPositionASC($invoice);

        foreach ($purchaseInvoiceItems as $purchaseInvoiceItem)
        {
            $amount = $purchaseInvoiceItem->getAmountTtc();
            $amountPaid = $purchaseInvoiceItem->getAmountPaid();

            $balance = $amount - $amountPaid;

            // check if $balance is less than $settlementAmount
            if ($balance < $settlementAmount)
            {
                // set amount Paid equal to amount Paid + $balance
                $purchaseInvoiceItem->setAmountPaid($amountPaid + $balance);

                // set balance to 0 because it is settle
                $purchaseInvoiceItem->setBalance(0);

                // set is Paid = true
                $purchaseInvoiceItem->setIsTreat(true);

                $settlementAmount = $settlementAmount - $balance;

                /*// amount = $balance
                if($purchaseInvoiceItem->getItem()->getFee()->getBudgetLine())
                {
                    // Find corresponding budget
                    // budget line = $purchaseInvoiceItem->getItem()->getFee()->getBudgetLine()
                    // budget exercise ?
                }*/
            }
            elseif ($balance > $settlementAmount)
            {
                // check if $balance is greater than $settlementAmount

                // set amount Paid equal to amount Paid + $settlementAmount
                $purchaseInvoiceItem->setAmountPaid($amountPaid + $settlementAmount);
                $purchaseInvoiceItem->setBalance($balance - $settlementAmount);

                // set is Paid = false
                $purchaseInvoiceItem->setIsTreat(false);

                $settlementAmount = 0;
                // break;

                // amount = $settlementAmount
            }
            elseif ($balance == $settlementAmount)
            {
                // check if $balance is equal to $settlementAmount

                // set amount Paid equal to amount Paid + $balance
                $purchaseInvoiceItem->setAmountPaid($amountPaid + $balance);

                // set balance to 0 because it is settle
                $purchaseInvoiceItem->setBalance(0);

                // set is Paid = true
                $purchaseInvoiceItem->setIsTreat(true);

                $settlementAmount = 0;
                // break;

                // amount = $balance
            }

        }

        $purchaseSettlement->setIsTreat(true);

        // other invoice status update
        $invoice->setOtherStatus('settlement');

        $entityManager->flush();

        return $this->json(['hydra:member' => $purchaseSettlement]);
    }

    /*public function budgetTransaction(Budget $budget, EntityManagerInterface $entityManager, $amount)
    {
        $budgetTransaction = new Needs();

        $budgetTransaction->setReference('123');
        $budgetTransaction->setBudget($budget);
        $budgetTransaction->setExercise($budget->getExercise());
        $budgetTransaction->setValidatedAmount($amount);
        $budgetTransaction->setReason('Income');

        $budgetTransaction->setInstitution($this->getUser()->getInstitution());
        $budgetTransaction->setUser($this->getUser());
        $budgetTransaction->setYear($this->getUser()->getCurrentYear());

        $entityManager->flush();
    }*/

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

    function generer_numero_unique() {
        // Génère un nombre aléatoire entre 10000 et 99999 (inclus)
        $numero_unique = rand(10000, 99999);
        return $numero_unique;
    }

}
