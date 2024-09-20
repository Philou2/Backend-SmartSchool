<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleSettlement;
use App\Entity\Partner\SupplierHistory;
use App\Entity\Security\User;
use App\Entity\Treasury\BankHistory;
use App\Entity\Treasury\CashDeskHistory;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
use App\Repository\School\Finance\SupplierHistoryRepository;
use App\Repository\Setting\Finance\PaymentGatewayRepository;
use App\Repository\Setting\Finance\PaymentMethodRepository;
use App\Repository\Treasury\BankAccountRepository;
use App\Repository\Treasury\BankHistoryRepository;
use App\Repository\Treasury\BankRepository;
use App\Repository\Treasury\CashDeskHistoryRepository;
use App\Repository\Treasury\CashDeskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class CreateValidatePurchaseReturnInvoiceController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             SaleSettlementRepository $saleSettlementRepository,
                             EntityManagerInterface $entityManager,
                             CashDeskRepository $cashDeskRepository,
                             CashDeskHistoryRepository $cashDeskHistoryRepository,
                             SupplierHistoryRepository $supplierHistoryRepository,
                             PaymentMethodRepository $paymentMethodRepository,
                             BankHistoryRepository $bankHistoryRepository,
                             BankAccountRepository $bankAccountRepository,
                             BankRepository $bankRepository,
                             PaymentGatewayRepository $paymentGatewayRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $saleReturnInvoice = $saleReturnInvoiceRepository->find($id);
        if (!$saleReturnInvoice){
            return new JsonResponse(['hydra:description' => 'This return invoice is not found.'], 404);
        }

        $requestData = json_decode($request->getContent(), true);
        $settlement = new SaleSettlement();

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $requestData['paymentMethod']);
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object


        $settlement->setPaymentMethod($paymentMethod);
        $settlement->setSettleAt(new \DateTimeImmutable($requestData['settleAt']));

        $settlement->setStudentRegistration($saleReturnInvoice->getStudentRegistration());
        $settlement->setSupplier($saleReturnInvoice->getSupplier());

        if (isset($requestData['paymentGateway']) && $requestData['paymentGateway']){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['paymentGateway']);
            $filterId = intval($filter);
            $paymentGateway = $paymentGatewayRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setPaymentGateway($paymentGateway);
        }
        
        if (isset($requestData['bank']) && $requestData['bank']){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['bank']);
            $filterId = intval($filter);
            $bank = $bankRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setBank($bank);
        }
        
        $uniqueNumber = $this->generer_numero_unique();
        $uniqueNumber = 'SET' . $uniqueNumber;

        $settlement->setReference($uniqueNumber);

        if($requestData['amountPay'] <= 0 ){
            return new JsonResponse(['hydra:title' => 'Amount paid can not be less than or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if ($requestData['amountPay'] > $saleReturnInvoice->getVirtualBalance())
        {
            return new JsonResponse(['hydra:title' => 'Amount paid can not be more than balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        $settlement->setAmountPay($requestData['amountPay']);
        $settlement->setAmountRest($saleReturnInvoice->getVirtualBalance() - $requestData['amountPay']);

        $saleReturnInvoice->setVirtualBalance($saleReturnInvoice->getVirtualBalance() - $settlement->getAmountPay());

        $settlement->setSaleReturnInvoice($saleReturnInvoice);

        $entityManager->persist($settlement);


        //validation

        if (!$settlement->getSupplier())
        {
            return new JsonResponse(['hydra:title' => 'Supplier not found'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        $amount = $requestData['amountPay'];

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $requestData['paymentMethod']);
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        if ($paymentMethod->isIsCashDesk()){
            $userCashDesk = $cashDeskRepository->findOneBy(['operator' => $this->getUser(), 'institution' => $this->getUser()->getInstitution()]);
            if (!$userCashDesk)
            {
                return new JsonResponse(['hydra:title' => 'user_is_not_cash_desk'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }


            $sendCashDesk = $cashDeskRepository->findOneBy(['operator' => $this->getUser()]);

            // Cash Desk
            if ($sendCashDesk !== $cashDeskRepository->findOneBy(['operator' => $this->getUser(), 'institution' => $this->getUser()->getInstitution()]))
            {
                return new JsonResponse(['hydra:title' => 'cash_desk_user_not_found'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            if (!$sendCashDesk->isIsOpen())
            {
                return new JsonResponse(['hydra:title' => 'cash_desk_not_open'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
            }

            // Cashier Adjustment Start
            $cashDesk = $sendCashDesk;
            // $operationCategory = $cashDeskOperation->getOperationCategory();


            // Write cash desk history
            $cashDeskHistory = new CashDeskHistory();
            $cashDeskHistory->setCashDesk($cashDesk);
            $cashDeskHistory->setReference($settlement->getReference());
            $cashDeskHistory->setDescription('settlement DEPOSIT');
            $cashDeskHistory->setDebit(0);
            $cashDeskHistory->setCredit($amount);
            // balance : en bas
            $cashDeskHistory->setDateAt(new \DateTimeImmutable());

            $cashDeskHistory->setInstitution($this->getUser()->getInstitution());
            $cashDeskHistory->setUser($this->getUser());
            $cashDeskHistory->setYear($this->getUser()->getCurrentYear());
            $entityManager->persist($cashDeskHistory);

            // Update cash desk daily deposit balance
            $cashDesk->setDailyDepositAmount($cashDesk->getDailyDepositAmount() + $amount);

            // Update cash desk balance
            $cashDeskHistories = $cashDeskHistoryRepository->findBy(['cashDesk' => $cashDesk]);

            $debit = 0; $credit = $amount;

            foreach ($cashDeskHistories as $item)
            {
                $debit += $item->getDebit();
                $credit += $item->getCredit();
            }

            $balance = $credit - $debit ;

            $cashDeskHistory->setBalance($balance);
            $cashDesk->setBalance($balance);

            $entityManager->flush();
        }

        if (isset($requestData['bank']) && $requestData['bank']){
            // Write bank history
            $bankHistory = new BankHistory();

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['bankAccount']);
            $filterId = intval($filter);
            $bankAccount = $bankAccountRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $bankHistory->setBankAccount($bankAccount);
            $bankHistory->setReference($requestData['bankAccount']. ' - '. 'CREDIT DEPOSIT');
            $bankHistory->setDescription('settlement DEPOSIT');
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

            $balance = $credit - $debit;

            $bankHistory->setBalance($balance);
            $bankAccount->setBalance($balance);
            $entityManager->flush();
        }


        $supplierHistory = new SupplierHistory();
        $supplierHistory->setSupplier($settlement->getSupplier());
        $supplierHistory->setReference($settlement->getReference());
        $supplierHistory->setUser($this->getUser());
        $supplierHistory->setInstitution($this->getUser()->getInstitution());
        $supplierHistory->setYear($this->getUser()->getCurrentYear());

        // Update customer history balance
        $customerHistories = $supplierHistoryRepository->findBy(['supplier' => $settlement->getSupplier()]);

        $debit = 0; $credit = $amount;

        foreach ($customerHistories as $item)
        {
            $debit += $item->getDebit();
            $credit += $item->getCredit();
        }

        $balance = $credit - $debit  ;

        $supplierHistory->setBalance($balance);
        $supplierHistory->setCredit(0);
        $supplierHistory->setDebit($amount);
        $supplierHistory->setDescription($settlement->getNote(). '-'. 'SETTLEMENT');
        $entityManager->persist($supplierHistory);


        //$settlement->getSaleReturnInvoice()?->setAmountPaid($settlement->getInvoice()->getAmountPaid() + $settlement->getAmountPay());
        $settlement->getSaleReturnInvoice()?->setAmountPaid($settlement->getSaleReturnInvoice()->getAmountPaid() + $requestData['amountPay']);
        $settlement->getSaleReturnInvoice()?->setBalance($settlement->getSaleReturnInvoice()->getTtc() - $settlement->getSaleReturnInvoice()->getAmountPaid());


        $settlement->setIsValidate(true);
        $settlement->setValidateAt(new \DateTimeImmutable());
        $settlement->setValidateBy($this->getUser());

        $entityManager->flush();


        return $this->json(['hydra:member' => $settlement]);
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
    function generer_numero_unique() {
        // Génère un nombre aléatoire entre 10000 et 99999 (inclus)
        $numero_unique = rand(10000, 99999);
        return $numero_unique;
    }

}
