<?php

namespace App\Controller\School\Schooling\Configuration;

use App\Entity\School\Schooling\Configuration\Fee;
use App\Entity\Security\User;
use App\Repository\Budget\BudgetLineRepository;
use App\Repository\School\Schooling\Configuration\CostAreaRepository;
use App\Repository\School\Schooling\Configuration\CycleRepository;
use App\Repository\School\Schooling\Configuration\LevelRepository;
use App\Repository\School\Schooling\Configuration\PensionSchemeRepository;
use App\Repository\School\Schooling\Configuration\SchoolClassRepository;
use App\Repository\School\Schooling\Configuration\SchoolRepository;
use App\Repository\School\Schooling\Configuration\SpecialityRepository;
use App\Repository\School\Schooling\Configuration\TrainingTypeRepository;
use App\Repository\Security\Institution\InstitutionRepository;
use App\Repository\Security\Session\YearRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class PostFeeController extends AbstractController
{
    public function __construct( private readonly TokenStorageInterface $tokenStorage,)
    {
    }

    public function __invoke(mixed $data, SpecialityRepository $specialityRepository, TrainingTypeRepository $trainingTypeRepository, CycleRepository $cycleRepository, LevelRepository $levelRepository, BudgetLineRepository $budgetLineRepository, EntityManagerInterface $entityManager, Request $request, InstitutionRepository $institutionRepository, YearRepository $yearRepository, CostAreaRepository $areaRepository,
                             SchoolRepository $schoolRepository, SchoolClassRepository $schoolClassRepository, PensionSchemeRepository $pensionSchemeRepository)
    {
        $requestData = json_decode($request->getContent(), true);

        $fee = new Fee();
        $fee->setCode($requestData['code']);
        $fee->setName($requestData['name']);
        $fee->setAmount($requestData['amount']);
        $fee->setPosition($requestData['position']);

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $requestData['year']);
        $filterId = intval($filter);
        $year = $yearRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        $fee->setYear($year);

        if (isset($requestData['school'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['school']);
            $filterId = intval($filter);
            $school = $schoolRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setSchool($school);
        }

        // START: Filter the uri to just take the id and pass it to our object
        $filter = preg_replace("/[^0-9]/", '', $requestData['costArea']);
        $filterId = intval($filter);
        $costArea = $areaRepository->find($filterId);
        // END: Filter the uri to just take the id and pass it to our object

        $fee->setCostArea($costArea);

        if (isset($requestData['pensionScheme'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['pensionScheme']);
            $filterId = intval($filter);
            $pensionSchema = $pensionSchemeRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setPensionScheme($pensionSchema);
        }

        if (isset($requestData['paymentDate']) || $requestData['paymentDate'] !== null){
            $fee->setPaymentDate(new \DateTimeImmutable($requestData['paymentDate']));
        }

        $fee->setUser($this->getUser());
        $fee->setYear($this->getUser()->getCurrentYear());

        $fee->setInstitution($this->getUser()->getInstitution());

        // Les champs facultatifs

        if (isset($requestData['class'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['class']);
            $filterId = intval($filter);
            $class = $schoolClassRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setClass($class);
        }

        if (isset($requestData['budgetLine'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['budgetLine']);
            $filterId = intval($filter);
            $budgetLine = $budgetLineRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setBudgetLine($budgetLine);
        }

        if (isset($requestData['speciality'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['speciality']);
            $filterId = intval($filter);
            $speciality = $specialityRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setSpeciality($speciality);
        }

        if (isset($requestData['cycle'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['cycle']);
            $filterId = intval($filter);
            $cycle = $cycleRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setCycle($cycle);
        }

        if (isset($requestData['level'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['level']);
            $filterId = intval($filter);
            $level = $levelRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setLevel($level);
        }

        if (isset($requestData['trainingType'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $requestData['trainingType']);
            $filterId = intval($filter);
            $trainingType = $trainingTypeRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $fee->setTrainingType($trainingType);
        }

        $entityManager->persist($fee);

        /*$item = new Item();
        $item->setFee($fee);
        $item->setName($fee->getName());
        $item->setReference($fee->getCode());
        $item->setPrice($fee->getAmount());
        $item->setCost($fee->getAmount());
        $item->setPosition($fee->getPosition());

        $item->setUser($this->getUser());
        $item->setYear($this->getUser()->getCurrentYear());

        $item->setInstitution($this->getUser()->getInstitution());

        $entityManager->persist($item);*/

        $entityManager->flush();

        return $fee;
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
