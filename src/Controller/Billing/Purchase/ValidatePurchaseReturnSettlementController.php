<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Partner\SupplierHistory;
use App\Entity\Security\User;
use App\Entity\Treasury\BankHistory;
use App\Entity\Treasury\CashDeskHistory;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
use App\Repository\School\Finance\SupplierHistoryRepository;
use App\Repository\Setting\Finance\PaymentMethodRepository;
use App\Repository\Treasury\BankAccountRepository;
use App\Repository\Treasury\BankHistoryRepository;
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
class ValidatePurchaseReturnSettlementController extends AbstractController
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
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $settlement = $saleSettlementRepository->find($id);
        if (!$settlement){
            return new JsonResponse(['hydra:description' => 'This settlement is not found.'], 404);
        }

        $requestData = json_decode($request->getContent(), true);

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
            $cashDeskHistory->setDescription($requestData['note']. ' '. 'SETTLEMENT');
            $cashDeskHistory->setDebit($amount);
            $cashDeskHistory->setCredit(0);
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

            $debit = $amount; $credit = 0;

            foreach ($cashDeskHistories as $item)
            {
                $debit += $item->getDebit();
                $credit += $item->getCredit();
            }

            $balance =$debit - $credit;

            $cashDeskHistory->setBalance($balance);
            $cashDesk->setBalance($balance);

            $entityManager->flush();
        }

        if ($requestData['bank']){
            // Write bank history
            $bankHistory = new BankHistory();

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['bankAccount']);
            $filterId = intval($filter);
            $bankAccount = $bankAccountRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $bankHistory->setBankAccount($bankAccount);
            $bankHistory->setReference($requestData['bankAccount']. ' - '. 'DEPOSIT');
            $bankHistory->setDescription('settlement DEPOSIT');
            $bankHistory->setDebit($amount);
            $bankHistory->setCredit(0);
            // balance : en bas
            $bankHistory->setDateAt(new \DateTimeImmutable());

            $bankHistory->setInstitution($this->getUser()->getInstitution());
            $bankHistory->setYear($this->getUser()->getCurrentYear());
            $bankHistory->setUser($this->getUser());
            $entityManager->persist($bankHistory);



            // Update bank balance
            $bankHistories = $bankHistoryRepository->findBy(['bankAccount' => $bankAccount]);

            $debit = $amount; $credit = 0;

            foreach ($bankHistories as $item)
            {
                $debit += $item->getDebit();
                $credit += $item->getCredit();
            }

            $balance =$debit - $credit;

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

        // Update supplier history balance
        $customerHistories = $supplierHistoryRepository->findBy(['supplier' => $settlement->getSupplier()]);

        $debit = 0; $credit = $amount;

        foreach ($customerHistories as $item)
        {
            $debit += $item->getDebit();
            $credit += $item->getCredit();
        }

        $balance =$credit - $debit;

        $supplierHistory->setBalance($balance);
        $supplierHistory->setCredit($amount);
        $supplierHistory->setDebit(0);
        $supplierHistory->setDescription($settlement->getNote(). '-'. 'SETTLEMENT');
        $entityManager->persist($supplierHistory);


        $settlement->getInvoice()?->setAmountPaid($settlement->getInvoice()->getAmountPaid() + $settlement->getAmountPay());
        $settlement->getInvoice()?->setBalance($settlement->getInvoice()->getTtc() - $settlement->getInvoice()->getAmountPaid());


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

}
