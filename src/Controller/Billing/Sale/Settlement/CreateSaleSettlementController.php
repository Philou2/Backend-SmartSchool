<?php

namespace App\Controller\Billing\Sale\Settlement;

use App\Entity\Billing\Sale\SaleSettlement;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleInvoiceRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
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
class CreateSaleSettlementController extends AbstractController
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
                            SaleInvoiceItemRepository $saleInvoiceItemRepository,
                            SaleInvoiceRepository $saleInvoiceRepository,
                            PaymentMethodRepository $paymentMethodRepository,
                            PaymentGatewayRepository $paymentGatewayRepository,
                            CashDeskRepository $cashDeskRepository,
                            BankRepository $bankRepository,
                            CustomerRepository $customerRepository,
                            SaleSettlementRepository $saleSettlementRepository,
                            BankAccountRepository $bankAccountRepository,
                            FileUploader $fileUploader
                             ): JsonResponse
    {
        $request = Request::createFromGlobals();

        $uploadedFile = $request->files->get('file');

        $settlement = new SaleSettlement();

        if($request->get('amountPay') <= 0 ){
            return new JsonResponse(['hydra:title' => 'Amount paid can not be less or equal to zero'], Response::HTTP_BAD_REQUEST, ['Content-Type', 'application/json']);
        }

        if ($request->get('paymentGateway') !== null && $request->get('paymentGateway')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('paymentGateway'));
            $filterId = intval($filter);
            $paymentGateway = $paymentGatewayRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setPaymentGateway($paymentGateway);
        }

        if ($request->get('cashDesk') !== null && $request->get('cashDesk')){

            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $request->get('cashDesk'));
            $filterId = intval($filter);
            $cashDesk = $cashDeskRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $settlement->setCashDesk($cashDesk);
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

        $generateSettlement = $saleSettlementRepository->findOneBy([], ['id' => 'DESC']);

        if (!$generateSettlement){
            $uniqueNumber = 'SAL/SET/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateSettlement->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'SAL/SET/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $settlement->setReference($uniqueNumber);


        // $settlement->setNote($invoiceData['note']);
        $settlement->setAmountPay($request->get('amountPay'));
        $settlement->setSettleAt(new \DateTimeImmutable($request->get('settleAt')));

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('paymentMethod'));
        $filterId = intval($filter);
        $paymentMethod = $paymentMethodRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $request->get('customer'));
        $filterId = intval($filter);
        $customer = $customerRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        $settlement->setPaymentMethod($paymentMethod);
        $settlement->setCustomer($customer);

        $settlement->setUser($this->getUser());

        $settlement->setInstitution($this->getUser()->getInstitution());
        $settlement->setBranch($this->getUser()->getBranch());
        $settlement->setYear($this->getUser()->getCurrentYear());

        // upload the file and save its filename
        if ($uploadedFile){
            $settlement->setPicture($fileUploader->upload($uploadedFile));
            $settlement->setFileName($request->get('fileName'));
            $settlement->setFileType($request->get('fileType'));
            $settlement->setFileSize($request->get('fileSize'));
        }

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
