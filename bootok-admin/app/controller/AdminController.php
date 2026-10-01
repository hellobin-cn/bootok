<?php

namespace app\controller;

use app\service\AdminService;
use DI\Attribute\Inject;
use support\Request;
use support\Response;

class AdminController extends Controller
{
    #[Inject]
    private ?AdminService $adminService = null;

    public function login(Request $request): Response
    {
        return $this->uniResponse($this->adminService->login($request->post('username'), $request->post('password')));
    }

    public function logout(): Response
    {
        return $this->uniResponse($this->adminService->logout($this->adminId));
    }

    public function updatePassword(Request $request): Response
    {
        return $this->uniResponse($this->adminService->updatePassword(
            $this->adminId,
            $request->post('old_password'),
            $request->post('new_password'),
            $request->post('re_password')
        ));
    }
}
