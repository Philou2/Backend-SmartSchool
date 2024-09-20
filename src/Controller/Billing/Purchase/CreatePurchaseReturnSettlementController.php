<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleSettlement;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use App\Repository\Setting\Finance\PaymentGatewayRepository;
use App\Repository\Setting\Finance\PaymentMethodRepository;
use App\Repository\Treasury\BankRepository;
use App\Repository\Treasury\CashDeskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class CreatePurchaseReturnSettlementController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                private readonly EntityManagerInterface $entityManager
                                )
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             PaymentMethodRepository $paymentMethodRepository,
                             PaymentGatewayRepository $paymentGatewayRepository,
                             CashDeskRepository $cashDeskRepository,
                             BankRepository $bankRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $supplierSaleReturnInvoice = $saleReturnInvoiceRepository->find($id);
        if (!$supplierSaleReturnInvoice){
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
        $settlement->setAmountPay($requestData['amountPay']);
        $settlement->setSettleAt(new \DateTimeImmutable($requestData['settleAt']));

        $settlement->setStudentRegistration($supplierSaleReturnInvoice->getStudentRegistration());
        $settlement->setSupplier($supplierSaleReturnInvoice->getSupplier());

        if (isset($requestData['paymentGateway']) && $requestData['paymentGateway']){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['paymentGateway']);
            $filterId = intval($filter);
            $paymentGateway = $paymentGatewayRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setPaymentGateway($paymentGateway);
        }

        if (isset($requestData['cashDesk']) && $requestData['cashDesk']){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['cashDesk']);
            $filterId = intval($filter);
            $cashDesk = $cashDeskRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setCashDesk($cashDesk);
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

        if ($requestData['amountPay'] > $supplierSaleReturnInvoice->getVirtualBalance())
        {
            return new JsonResponse(['hydra:title' => 'Amount paid can not be more than balance'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if ($requestData['amountPay'] <= $supplierSaleReturnInvoice->getAmount()){
            $settlement->setAmountPay($requestData['amountPay']);
            $settlement->setAmountRest($supplierSaleReturnInvoice->getAmount() - $requestData['amountPay']);
        }
        else{
            $settlement->setAmountPay($requestData['amountPay']);
            $settlement->setAmountRest(0);
        }

        $supplierSaleReturnInvoice->setVirtualBalance($supplierSaleReturnInvoice->getVirtualBalance() - $settlement->getAmountPay());

        $settlement->setIsValidate(false);
        $settlement->setUser($this->getUser());
        $settlement->setYear($this->getUser()->getCurrentYear());

        $settlement->setSaleReturnInvoice($supplierSaleReturnInvoice);

        $this->entityManager->persist($settlement);
        $this->entityManager->flush();

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
