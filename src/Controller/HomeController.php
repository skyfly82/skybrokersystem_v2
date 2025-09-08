<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        // Zachowujemy dotychczasową stronę startową do dev/testów pod '/'
        return $this->render('home.html.twig');
    }

    #[Route('/web', name: 'app_home_web', methods: ['GET'])]
    public function web(): Response
    {
        // Nowa profesjonalna strona główna dostępna pod '/web'
        return $this->render('web/home.html.twig');
    }

    #[Route('/auth', name: 'app_auth', methods: ['GET'])]
    public function auth(): Response
    {
        return $this->render('auth.html.twig');
    }

    #[Route('/login', name: 'app_login_web', methods: ['GET'])]
    public function loginWeb(): Response
    {
        return $this->render('login.html.twig');
    }

    #[Route('/login/customer', name: 'app_login_customer_web', methods: ['GET'])]
    public function loginCustomerWeb(): Response
    {
        return $this->render('login_customer.html.twig');
    }

    #[Route('/login/admin', name: 'app_login_admin_web', methods: ['GET'])]
    public function loginAdminWeb(): Response
    {
        return $this->render('login_admin.html.twig');
    }

    #[Route('/register', name: 'app_register_web', methods: ['GET'])]
    public function registerWeb(): Response
    {
        return $this->render('register.html.twig');
    }

    #[Route('/forgot', name: 'app_forgot_web', methods: ['GET'])]
    public function forgotWeb(): Response
    {
        return $this->render('forgot.html.twig');
    }
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('dashboard.html.twig');
    }
}
