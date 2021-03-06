<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Front\Controller;

use GarnierCaptcha\Event\GarnierCaptchaEvents;
use GarnierCaptcha\Event\GarnierCaptchaCheckEvent;
use GarnierShop\GarnierShop;
use Thelia\Controller\Front\BaseFrontController;
//use Thelia\Core\Event\Contact\ContactEvent;
//use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Class ContactController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContactController extends BaseFrontController
{
    /**
     * send contact message
     */
    public function sendAction()
    {
        $contactForm = $this->createForm(FrontForm::CONTACT);

        try {
            $form = $this->validateForm($contactForm);

            //$event = new ContactEvent($form);

            //$this->dispatch(TheliaEvents::CONTACT_SUBMIT, $event);

            $userEmail = $form->get('email')->getData();
            $userName = $form->get('name')->getData();
            $userTheme = $form->get('theme')->getData();
            $userSubject = $form->get('subject')->getData();
            $userMessage = $form->get('message')->getData();

            // -DC- Debug post
            //Tlog::getInstance()->error(sprintf('Contact form : %s', print_r($_POST, true)));

            // -DC- Check captcha on server side
            $request = $this->getRequest();
            $captchaResponse = $request->request->get('g-recaptcha-response');
            $remoteIp = $request->server->get('REMOTE_ADDR');
            $checkCaptchaEvent = new GarnierCaptchaCheckEvent($captchaResponse, $remoteIp);
            $this->dispatch(GarnierCaptchaEvents::CHECK_CAPTCHA_EVENT, $checkCaptchaEvent);
            if ($checkCaptchaEvent->isHuman() == false) {
            	$error = GarnierShop::Translate('Invalid captcha');
            	throw new FormValidationException($error);
            }

            // -DC- Contact email
            $storeEmailDefault = ConfigQuery::read('contact_email', null);
            $configEmail = sprintf('contactform_%s_email', $userTheme);
            $storeEmail = ConfigQuery::read($configEmail, null);
            if (empty($storeEmail)) {
            	$storeEmail = $storeEmailDefault;
            }
            //$storeEmail = ConfigQuery::getStoreEmail();
            $storeName = ConfigQuery::getStoreName();

            $from = [ $userEmail => $userName ];
            $to = [ $storeEmail => $storeName ];
            $subject = sprintf('[Formulaire contact] %s', $userSubject);
            $htmlBody = '';
            $textBody = $userMessage;
            $cc = [];
            $bcc = [];
            $replyTo = [ ]; // $userEmail => $userName ];

            $this->getMailer()->sendSimpleEmailMessage(
                $from,
                $to,
                $subject,
                $htmlBody,
                $textBody,
                $cc,
                $bcc,
                $replyTo
            );

            if ($contactForm->hasSuccessUrl()) {
                return $this->generateSuccessRedirect($contactForm);
            }

            return $this->generateRedirectFromRoute('contact.success');

        } catch (FormValidationException $e) {
            $error_message = $e->getMessage();
        }

        Tlog::getInstance()->error(sprintf('Error during sending contact mail : %s', $error_message));

        $contactForm->setErrorMessage($error_message);

        $this->getParserContext()
            ->addForm($contactForm)
            ->setGeneralError($error_message)
        ;

        // Redirect to error URL if defined
        if ($contactForm->hasErrorUrl()) {
            return $this->generateErrorRedirect($contactForm);
        }
    }
}
