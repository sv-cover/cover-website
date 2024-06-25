<?php
namespace App\Controller;

require_once 'src/framework/controllers/Controller.php';

use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;

class SocietiesController extends \Controller
{
	protected $view_name = 'societies';

	public function run_found()
	{
		if (!get_auth()->logged_in())
			throw new \UnauthorizedException();

		$member = get_identity()->member();

		$data = [
			'email' => $member['email'],
			'phone' => $member['telefoonnummer'],
		];

		$form = $this->createFormBuilder($data)
			->add('society_name', TextType::class, [
				'label' => __('Society name'),
				'constraints' => new Assert\NotBlank(),
			])
			->add('society_purpose', TextareaType::class, [
				'label' => __('Purpose of the society'),
				'constraints' => new Assert\NotBlank(),
			])
			->add('founding_members', TextType::class, [
				'label' => __('Founding members'),
				'constraints' => new Assert\NotBlank(),
				'help' => __('Who are the founding members of the society?'),
			])
			->add('other_comments', TextareaType::class, [
				'label' => __('Other comments'),
				// allow it to be blank
				'required' => false,
				'help' => __('Anything else the Board should know when considering the request?'),
			])
			->add('email', EmailType::class, [
				'label' => __('Email'),
				'help' => __('We need to know how to contact you for questions!'),
				'constraints' => [new Assert\NotBlank(), new Assert\Email()],
			])
			->add('phone', TelType::class, [
				'label' => __('Phone number'),
				'help' => __('We need to know how to contact you for questions!'),
				'constraints' => [
					new Assert\NotBlank(),
					new AssertPhoneNumber(['defaultRegion' => 'NL']),
				],
			])
			->add('submit', SubmitType::class, [
				'label' => __('Submit proposal'),
			])
			->getForm();
		$form->handleRequest($this->get_request());

		if ($form->isSubmitted() && $form->isValid()) {
			$mail = parse_email_object("society_request.txt", ['data' => $form->getData(), 'member' => $member]);
			$mail->send(get_config_value('email_bestuur'));
			$_SESSION['alert'] = __('Society foundation requested! You should hear from the Board soon!');
			return $this->view->redirect($this->generate_url('societies'));
		}

		return $this->view->render('form.twig', ['form' => $form->createView()]);
	}

	public function run_index()
	{
		return $this->view->render('index.twig');
	}

	protected function run_impl()
	{
		$view = isset($_GET['view']) ? $_GET['view'] : 'index';
		return call_user_func([$this, 'run_' . $view]);
	}
}
