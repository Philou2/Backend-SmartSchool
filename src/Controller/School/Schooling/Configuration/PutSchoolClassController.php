<?php

namespace App\Controller\School\Schooling\Configuration;

use App\Entity\Security\User;
use App\Repository\School\Schooling\Configuration\ClassCategoryRepository;
use App\Repository\School\Schooling\Configuration\DepartmentRepository;
use App\Repository\School\Schooling\Configuration\GuardianshipRepository;
use App\Repository\School\Schooling\Configuration\LevelRepository;
use App\Repository\School\Schooling\Configuration\OptionRepository;
use App\Repository\School\Schooling\Configuration\RoomRepository;
use App\Repository\School\Schooling\Configuration\SchoolClassRepository;
use App\Repository\School\Schooling\Configuration\SchoolRepository;
use App\Repository\School\Schooling\Configuration\SpecialityRepository;
use App\Repository\School\Schooling\Configuration\TrainingTypeRepository;
use App\Repository\Security\Institution\BranchRepository;
use App\Repository\Security\SystemSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class PutSchoolClassController extends AbstractController
{
    public function __construct( private readonly TokenStorageInterface $tokenStorage,)
    {
    }

    public function __invoke(mixed $data,Request $request, SchoolClassRepository $schoolClassRepository, SchoolRepository $schoolRepository,
                             BranchRepository $branchRepository, SystemSettingsRepository $systemSettingsRepository, GuardianshipRepository $guardianshipRepository, DepartmentRepository $departmentRepository, ClassCategoryRepository $classCategoryRepository,
                             SpecialityRepository $specialityRepository, LevelRepository $levelRepository, TrainingTypeRepository $trainingTypeRepository, RoomRepository $roomRepository, OptionRepository $optionRepository)
    {
        $requestData = json_decode($request->getContent(), true);
        $school = !isset($requestData['school']) ? null : $schoolRepository->find($this->getIdFromApiResourceId($requestData['school']));
        $guardianship = !isset($requestData['guardianship']) ? null : $guardianshipRepository->find($this->getIdFromApiResourceId($requestData['guardianship']));
        $department = !isset($requestData['department']) ? null : $departmentRepository->find($this->getIdFromApiResourceId($requestData['department']));
        $classCategory = !isset($requestData['classCategory']) ? null : $classCategoryRepository->find($this->getIdFromApiResourceId($requestData['classCategory']));
        $speciality = !isset($requestData['speciality']) ? null : $specialityRepository->find($this->getIdFromApiResourceId($requestData['speciality']));
        $level = !isset($requestData['level']) ? null : $levelRepository->find($this->getIdFromApiResourceId($requestData['level']));
        $trainingType = !isset($requestData['trainingType']) ? null : $trainingTypeRepository->find($this->getIdFromApiResourceId($requestData['trainingType']));
        $room = !isset($requestData['mainRoom']) ? null : $roomRepository->find($this->getIdFromApiResourceId($requestData['mainRoom']));
        $option = !isset($requestData['registrantOption']) ? null : $optionRepository->find($this->getIdFromApiResourceId($requestData['registrantOption']));

        $code = $requestData['code'];

        $systemSettings = $systemSettingsRepository->findOneBy([]);

        $duplicateCheckCode = $schoolClassRepository->findOneBy(['code' => $code, 'school' => $school]);
        if ($duplicateCheckCode && ($duplicateCheckCode != $data)) {
            return new JsonResponse(['hydra:description' => 'This code already exists in this school.'], 400);
        }

        $data->setCode($requestData['code']);
        $data->setDescription($requestData['description']);
        $data->setGuardianship($guardianship);
        $data->setDepartment($department);
        $data->setClassCategory($classCategory);
        $data->setSpeciality($speciality);
        $data->setLevel($level);
        $data->setTrainingType($trainingType);
        $data->setMainRoom($room);
        $data->setMaximumStudentNumber($requestData['maximumStudentNumber']);
        $data->setIsOptional($requestData['isOptional']);
        $data->setRegistrantOption($option);
        $data->setClassExam($requestData['classExam']);
        $data->setAgeLimit($requestData['ageLimit']);
        $data->setSimpleHourlyRate($requestData['simpleHourlyRate']);
        $data->setMultipleHourlyRate($requestData['multipleHourlyRate']);
        $data->setNextClass($requestData['nextClass']);
        if($systemSettings) {
            if ($systemSettings->isIsBranches()) {
                $data->setSchool($school);
            }
        }

        return $data;
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
