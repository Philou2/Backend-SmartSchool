<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Purchase\PurchaseSettlement;
use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Purchase\PurchaseSettlementRepository;
use App\Repository\Partner\CustomerRepository;
use App\Repository\Setting\Finance\PaymentGatewayRepository;
use App\Repository\Setting\Finance\PaymentMethodRepository;
use App\Repository\Treasury\BankAccountRepository;
use App\Repository\Treasury\BankRepository;
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
class PostPurchaseSettlementController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                private readonly EntityManagerInterface $entityManager
                                )
    {
    }

    public function jsondecode()
    {
        try {
            return file_get_contents('php://input') ?
                json_decode(file_get_contents('php://input'), false) :
                [];
        }catch (\Exception $ex)
        {
            return [];
        }

    }

    public function __invoke(
                            PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                            PurchaseInvoiceRepository $purchaseInvoiceRepository,
                            PaymentMethodRepository $paymentMethodRepository,
                            PaymentGatewayRepository $paymentGatewayRepository,
                            CashDeskRepository $cashDeskRepository,
                            BankRepository $bankRepository,
                            CustomerRepository $customerRepository,
                            PurchaseSettlementRepository $purchaseSettlementRepository,
                            BankAccountRepository $bankAccountRepository,
                            FileUploader $fileUploader
                             ): JsonResponse
    {
        $request = Request::createFromGlobals();

        $uploadedFile = $request->files->get('file');

        $purchaseSettlement = new PurchaseSettlement();

        if($request->get('amountPay') <= 0 ){
            return new JsonResponse(['hydra:title' => 'Amount paid can not be less or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if ($request->get('paymentGateway') !== null && $request->get('paymentGateway')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('paymentGateway'));
            $filterId = intval($filter);
            $paymentGateway = $paymentGatewayRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseSettlement->setPaymentGateway($paymentGateway);
        }

        if ($request->get('cashDesk') !== null && $request->get('cashDesk')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('cashDesk'));
            $filterId = intval($filter);
            $cashDesk = $cashDeskRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseSettlement->setCashDesk($cashDesk);
        }


        if ($request->get('bank') !== null && $request->get('bank')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bank'));
            $filterId = intval($filter);
            $bank = $bankRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseSettlement->setBank($bank);
        }

        if ($request->get('bankAccount') !== null && $request->get('bankAccount')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('bankAccount'));
            $filterId = intval($filter);
            $bankAccount = $bankAccountRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $purchaseSettlement->setBankAccount($bankAccount);
        }

        $generateSettlement = $purchaseSettlementRepository->findOneBy([], ['id' => 'DESC']);

        if (!$generateSettlement){
            $uniqueNumber = 'PUR/SET/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateSettlement->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'PUR/SET/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $purchaseSettlement->setReference($uniqueNumber);


        // $purchaseSettlement->setNote($invoiceData['note']);
        $purchaseSettlement->setAmountPay($request->get('amountPay'));
        $purchaseSettlement->setSettleAt(new \DateTimeImmutable($request->get('settleAt')));

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('paymentMethod'));
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('customer'));
        $filterId = intval($filter);
        $supplier = $customerRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        $purchaseSettlement->setPaymentMethod($paymentMethod);
        $purchaseSettlement->setSupplier($supplier);

        $purchaseSettlement->setUser($this->getUser());

        $purchaseSettlement->setInstitution($this->getUser()->getInstitution());
        $purchaseSettlement->setBranch($this->getUser()->getBranch());
        $purchaseSettlement->setYear($this->getUser()->getCurrentYear());

        // upload the file and save its filename
        if ($uploadedFile){
            $purchaseSettlement->setPicture($fileUploader->upload($uploadedFile));
            $purchaseSettlement->setFileName($request->get('fileName'));
            $purchaseSettlement->setFileType($request->get('fileType'));
            $purchaseSettlement->setFileSize($request->get('fileSize'));
        }

        $this->entityManager->persist($purchaseSettlement);
        $this->entityManager->flush();

        return $this->json(['hydra:member' => $purchaseSettlement]);
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
