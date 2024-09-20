<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\ConsignmentHour;
use App\Entity\Security\User;
use App\Repository\School\Exam\Configuration\SequenceRepository;
use App\Repository\School\Schooling\Configuration\SchoolClassRepository;
use App\Repository\School\Schooling\Configuration\SchoolRepository;
use App\Repository\School\Schooling\Discipline\ConsignmentHourRepository;
use App\Repository\School\Schooling\Discipline\MotifRepository;
use App\Repository\School\Schooling\Registration\StudentRegistrationRepository;
use App\Repository\Security\Institution\InstitutionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class PostConsignmentHourController extends AbstractController
{
    public function __construct(EntityManagerInterface $manager,
                                private readonly TokenStorageInterface $tokenStorage
    )
    {
    }

    public function __invoke(Request                       $request,
                             InstitutionRepository         $institutionRepository,
                             EntityManagerInterface        $manager,
                             ConsignmentHourRepository     $consignmentHoursRepository,
                             StudentRegistrationRepository $studRegistrationRepo,
                             SchoolRepository              $schoolRepository,
                             SchoolClassRepository         $schoolClassRepository,
                             SequenceRepository            $sequenceRepository,
                             MotifRepository               $motifRepository)
    {
        $consignmentHoursData = json_decode($request->getContent(), true);
        $studentRegistrationsSelected = $consignmentHoursData['studentRegistration'];

        $schoolId = !empty($consignmentHoursData['school']) ? $this->getIdFromApiResourceId($consignmentHoursData['school']) : null;
        $school = $schoolId ? $schoolRepository->find($schoolId) : null;

        $classId = !empty($consignmentHoursData['class']) ? $this->getIdFromApiResourceId($consignmentHoursData['class']) : null;
        $class = $classId ? $schoolClassRepository->find($classId) : null;

        $sequenceId = !empty($consignmentHoursData['sequence']) ? $this->getIdFromApiResourceId($consignmentHoursData['sequence']) : null;
        $sequence = $sequenceId ? $sequenceRepository->find($sequenceId) : null;

        $motifId = !empty($suspensionData['motif']) ? $this->getIdFromApiResourceId($suspensionData['motif']) : null;
        $motif = $motifId ? $motifRepository->find($motifId) : null;

        $startDate = new DateTime($consignmentHoursData['startDate']);
        $startTime = new DateTime($consignmentHoursData['startTime']);
        $endTime = new DateTime($consignmentHoursData['endTime']);
        $observations = $consignmentHoursData['observations'];


        foreach ($studentRegistrationsSelected as $studentRegistrationSelected) {
            // Setting to get Id
            $filter = preg_replace("/[^0-9]/", '', $studentRegistrationSelected);
            $filterId = intval($filter);
            $studentRegistrationTaken = $studRegistrationRepo->find($filterId);

            // New Student Registration
            $consignmentHours = new ConsignmentHour();

            $consignmentHours->addStudentRegistration($studentRegistrationTaken);
            $studentRegistrationTaken->addConsignmentHour($consignmentHours);

            $consignmentHours->setSchool($school);
            $consignmentHours->setSchoolClass($class);
            $consignmentHours->setSequence($sequence);
            $consignmentHours->setMotif($motif);
            $consignmentHours->setStartDate($startDate);
            $consignmentHours->setStartTime($startTime);
            $consignmentHours->setEndTime($endTime);
            $consignmentHours->setObservations($observations);

            $consignmentHours->setInstitution($this->getUser()->getInstitution());
            $consignmentHours->setUser($this->getUser());
            $consignmentHours->setYear($this->getUser()->getCurrentYear());

            $manager->persist($consignmentHours);

        }

        $manager->flush();
        return $consignmentHours;
    }


    public function getIdFromApiResourceId(string $apiId){
        $lastIndexOf = strrpos($apiId, '/');
        $id = substr($apiId, $lastIndexOf+1);
        return intval($id);
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
