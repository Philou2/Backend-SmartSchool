<?php
namespace App\State\Processor\School\Study\Program;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\School\Schooling\Registration\StudentCourseRegistration;
use App\Entity\School\Study\Program\ClassProgram;
use App\Entity\School\Study\Program\TeacherCourseRegistration;
use App\Entity\Security\User;
use App\Repository\School\Schooling\Registration\StudentCourseRegistrationRepository;
use App\Repository\School\Schooling\Registration\StudentRegistrationRepository;
use App\Repository\School\Study\Program\ClassProgramRepository;
use App\Repository\School\Study\Teacher\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PostClassProgramProcessor implements ProcessorInterface
{
    private EntityManagerInterface $manager;
    private ClassProgramRepository $classProgramRepo;
    private TeacherRepository $teacherRepo;
    private StudentRegistrationRepository $studentRegistrationRepo;

    public function __construct(
        private readonly ProcessorInterface $processor,
        private readonly TokenStorageInterface $tokenStorage,
        Request                             $request,
        EntityManagerInterface              $manager,
        ClassProgramRepository              $classProgramRepo,
        TeacherRepository                   $teacherRepo,
        StudentRegistrationRepository       $studentRegistrationRepo,
        private readonly StudentCourseRegistrationRepository $studentCourseRegistrationRepository
    )
    {
        $this->req = $request;
        $this->manager = $manager;
        $this->classProgramRepo = $classProgramRepo;
        $this->teacherRepo = $teacherRepo;
        $this->studentRegistrationRepo = $studentRegistrationRepo;
    }

    public function getIdFromApiResourceId(string $apiId)
    {
        $lastIndexOf = strrpos($apiId, '/');
        $id = substr($apiId, $lastIndexOf + 1);
        return intval($id);
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // validation from here
        if (!$data instanceof ClassProgram){
            return 0;
        }

        $programData = json_decode($this->req->getContent(), true);
        $existingClassProgram = $this->classProgramRepo->findAll();

        $data->setInstitution($this->getUser()->getInstitution());

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        foreach ($existingClassProgram as $existingProgram) {
            foreach ($daysOfWeek as $day) {
                foreach (['Cm', 'Tp', 'Td'] as $timeSlot) {
                    $time = $data->{'get' . $day . 'Start' . $timeSlot}() ? $data->{'get' . $day . 'Start' . $timeSlot}()->format('H:i:s') : '';
                    $time1 = $existingProgram->{'get' . $day . 'Start' . $timeSlot}() ? $existingProgram->{'get' . $day . 'Start' . $timeSlot}()->format('H:i:s') : '';
                    $time2 = $data->{'get' . $day . 'End' . $timeSlot}() ? $data->{'get' . $day . 'End' . $timeSlot}()->format('H:i:s') : '';
                    $time3 = $existingProgram->{'get' . $day . 'End' . $timeSlot}() ? $existingProgram->{'get' . $day . 'End' . $timeSlot}()->format('H:i:s') : '';

                    if($data->{'is' . $day . $timeSlot}()){
                        if (
                            $existingProgram->getClass() === $data->getClass() &&
                            $time1 == $time &&
                            $time3 == $time2
                        ) {
                            if (
                                $existingProgram->getNameuvc() == $data->getNameuvc() &&
                                ($existingProgram->{'getTeacher' . $timeSlot}() ? $existingProgram->{'getTeacher' . $timeSlot}()->getId() : '') == ($data->{'getTeacher' . $timeSlot}() ? $data->{'getTeacher' . $timeSlot}()->getId() : '') &&
                                $existingProgram->getPrincipalRoom()->getId() == $data->getPrincipalRoom()->getId()
                            ) {
                                return new JsonResponse(['hydra:description' => 'A Class Program with the same parameters already exists.'], 400);
                            }
                            elseif (
                                $time1 == $time &&
                                $time3 == $time2 &&
                                $existingProgram->getPrincipalRoom()->getId() == $data->getPrincipalRoom()->getId()
                            ) {
                                return new JsonResponse(['hydra:description' => 'A Class Program is already planned for this Room in this period.'], 400);
                            }
                            else {
                                return new JsonResponse(['hydra:description' => 'This period is already occupied.'], 400);
                            }

                        }

                    }
                    if ($time2 < $time) {
                        return new JsonResponse(['hydra:description' => 'The End Time MUST be greater than the Start Time.'], 400);
                    }
                }
            }
        }

        // COURSE SECTION
        $data->setInstitution($this->getUser()->getInstitution());
        $data->setUser($this->getUser());
        // COURSE SECTION END

        $this->manager->persist($data);


        // STUDENT COURSE REGISTRATION SECTION
        $isSubjectObligatory = $data->isIsSubjectObligatory();

        $year = $data->getYear();
        $class = $data->getClass();
        $school = $data->getSchool();
        $evaluationPeriod = $data->getEvaluationPeriod();
        $module = $data->getModule();

        if ($isSubjectObligatory === true) {
            $studentRegistrations = $this->studentRegistrationRepo->findBy(['currentYear'=>$year, 'currentClass'=>$class, 'school'=>$school]);
            foreach ($studentRegistrations as $studentRegistration) {

                $existingStudentCourseRegistration = $this->studentCourseRegistrationRepository->findOneBy([
                    'evaluationPeriod' => $evaluationPeriod,
                    'class' => $class,
                    'classProgram' => $data,
                    'StudRegistration' => $studentRegistration,
                ]);

                if (!$existingStudentCourseRegistration) {
                    $studentCourseRegistration = new StudentCourseRegistration();
                    $studentCourseRegistration->setClass($class);
                    $studentCourseRegistration->setClassProgram($data);
                    $studentCourseRegistration->setStudRegistration($studentRegistration);
                    $studentCourseRegistration->setSchool($school);
                    $studentCourseRegistration->setEvaluationPeriod($evaluationPeriod);
                    $studentCourseRegistration->setModule($module);

                    $studentCourseRegistration->setInstitution($this->getUser()->getInstitution());
                    $studentCourseRegistration->setUser($this->getUser());
                    $studentCourseRegistration->setYear($year);

                    $this->manager->persist($studentCourseRegistration);
                }

            }

        }
        // STUDENT COURSE REGISTRATION SECTION END



        // TEACHER COURSE REGISTRATION SECTION
        // Define the teacher types and their corresponding volume hours
        $teacherTypes = [
            // 'teacher' => 'vhEx',
            'teacherCm' => 'vhCm',
            'teacherTd' => 'vhTd',
            'teacherTp' => 'vhTp',
            'teacherMark' => 'vhEx'
        ];

        foreach ($teacherTypes as $teacherType => $volumeHour) {
            if (isset($programData[$teacherType]) && $programData[$teacherType]) {
                $teacherCourseRegistration = new TeacherCourseRegistration();

                $teacher = !isset($programData[$teacherType]) ? null : $this->teacherRepo->find($this->getIdFromApiResourceId($programData[$teacherType]));

                $teacherCourseRegistration->setTeacher($teacher);
                $teacherCourseRegistration->setCourse($data);
                $teacherCourseRegistration->setHourlyRateVolume($programData ? $programData[$volumeHour] : null);
                $teacherCourseRegistration->setType($teacherType);

                $teacherCourseRegistration->setInstitution($this->getUser()->getInstitution());
                $teacherCourseRegistration->setUser($this->getUser());
                $teacherCourseRegistration->setYear($year);

                $this->manager->persist($teacherCourseRegistration);
            }
        }
        // TEACHER COURSE REGISTRATION SECTION END


        // UPDATE COURSE
        $data->setIsChoiceStudCourseOpen($class->getIsChoiceStudentCourse());

        $this->manager->flush();
        // UPDATE COURSE END

        $result = $this->processor->process($data, $operation, $uriVariables, $context);

        return $result;

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
