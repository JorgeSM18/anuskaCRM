<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create', description: 'Crea un usuario (admin o normal)')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email de acceso')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre completo')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Contraseña (si se omite, se pide de forma oculta)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Darle rol de administrador');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) ($input->getOption('email') ?? $io->ask('Email'));
        $name = (string) ($input->getOption('name') ?? $io->ask('Nombre completo'));

        $password = (string) $input->getOption('password');
        if ('' === $password) {
            $q = (new Question('Contraseña'))->setHidden(true)->setHiddenFallback(false);
            $password = (string) $io->askQuestion($q);
        }

        if (!$email || !$name || !$password) {
            $io->error('Email, nombre y contraseña son obligatorios.');

            return Command::FAILURE;
        }

        if ($this->users->findOneBy(['email' => $email])) {
            $io->error(\sprintf('Ya existe un usuario con el email "%s".', $email));

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($name);
        $user->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : ['ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath().': '.$error->getMessage());
            }

            return Command::FAILURE;
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(\sprintf('Usuario "%s" creado%s.', $email, $user->isAdmin() ? ' como administrador' : ''));

        return Command::SUCCESS;
    }
}
