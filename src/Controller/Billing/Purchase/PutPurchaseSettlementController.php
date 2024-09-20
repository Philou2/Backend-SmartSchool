<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Purchase\PurchaseSettlement;
use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Purchase\PurchaseSettlementRepository;
use App\Repository\Partner\SupplierRepository;
use App\Repository\Setting\Finance\PaymentGatewayRepository;
use App\Repository\Setting\Finance\PaymentMethodRepository;
use App\Repository\Treasury\BankAccountRepository;
use App\Repository\Treasury\BankRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class PutPurchaseSettlementController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             PurchaseSettlementRepository $purchaseSettlementRepository,
                             EntityManagerInterface $entityManager,
                             PaymentMethodRepository $paymentMethodRepository,
                             BankAccountRepository $bankAccountRepository,
                             BankRepository $bankRepository,
                             SupplierRepository $supplierRepository,
                             FileUploader $fileUploader,
                             PaymentGatewayRepository $paymentGatewayRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');


        $uploadedFile = $request->files->get('file');

        $settlement = $purchaseSettlementRepository->find($id);
        if (!$settlement){
            return new JsonResponse(['hydra:description' => 'This settlement is not found.'], 404);
        }

        if(!$settlement instanceof PurchaseSettlement)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of settlement.'], 404);
        }

        if($request->get('amountPay') <= 0){
            return new JsonResponse(['hydra:description' => 'Amounted paid can not be less or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if ($request->get('paymentGateway') !== null && $request->get('paymentGateway')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('paymentGateway'));
            $filterId = intval($filter);
            $paymentGateway = $paymentGatewayRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setPaymentGateway($paymentGateway);
        }

        if ($request->get('bank') !== null && $request->get('bank')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bank'));
            $filterId = intval($filter);
            $bank = $bankRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setBank($bank);
        }

        if ($request->get('bankAccount') !== null && $request->get('bankAccount')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bankAccount'));
            $filterId = intval($filter);
            $bankAccount = $bankAccountRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setBankAccount($bankAccount);
        }

        if ($settlement->getInvoice()){
            $oldSettlementAmount = $request->get('oldAmount');

            $invoiceVirtualBalance = $settlement->getInvoice()->getVirtualBalance();

            $checkToSettle = $oldSettlementAmount + $invoiceVirtualBalance;

            if ($checkToSettle < $request->get('amountPay')){

                return new JsonResponse(['hydra:description' => 'Amount pay can not be more than balance'], 404);
            }


            $settlement->getInvoice()->setVirtualBalance($settlement->getInvoice()->getVirtualBalance() + $oldSettlementAmount - $request->get('amountPay'));
        }

        if ($settlement->getPurchaseReturnInvoice()){
            $oldSettlementAmount = $request->get('oldAmount');

            $invoiceVirtualBalance = $settlement->getPurchaseReturnInvoice()->getVirtualBalance();

            $checkToSettle = $oldSettlementAmount + $invoiceVirtualBalance;

            if ($checkToSettle < $request->get('amountPay')){

                return new JsonResponse(['hydra:description' => 'Amount pay can not be more than balance'], 404);
            }


            $settlement->getPurchaseReturnInvoice()->setVirtualBalance($settlement->getPurchaseReturnInvoice()->getVirtualBalance() + $oldSettlementAmount - $request->get('amountPay'));
        }

        $settlement->setSettleAt(new \DateTimeImmutable($request->get('settleAt')));
        //$settlement->setSettleAt(new \DateTimeImmutable($request->get('data')('settleAt')));
        $settlement->setAmountPay($request->get('amountPay'));
        // $settlement->setNote($request->get['data']['note']);

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('paymentMethod'));
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('customer'));
        $filterId = intval($filter);
        $customer = $supplierRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        $settlement->setSupplier($customer);


        //$settlement->setStudentRegistration($customer->getStudentRegistration());


        $settlement->setPaymentMethod($paymentMethod);


        // upload the file and save its filename
        if ($uploadedFile){
            $settlement->setPicture($fileUploader->upload($uploadedFile));
            $settlement->setFileName($request->get('fileName'));
            $settlement->setFileType($request->get('fileType'));
            $settlement->setFileSize($request->get('fileSize'));
        }

        $entityManager->persist($settlement);

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
