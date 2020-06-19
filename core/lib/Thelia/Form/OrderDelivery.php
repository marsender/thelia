<?php

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/**
 * **********************************************************************************
 */
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class OrderDelivery
 *
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderDelivery extends BaseForm
{

	protected function buildForm()
	{
		$this->formBuilder->add("delivery-address", "integer", array(
			"required" => true,
			"constraints" => array(
				new Constraints\NotBlank(),
				new Constraints\Callback(array(
					"methods" => array(
						array(
							$this,
							"verifyDeliveryAddress"
						)
					)
				))
			)
		))->add("delivery-module", "integer", array(
			"required" => true,
			"constraints" => array(
				new Constraints\NotBlank(),
				new Constraints\Callback(array(
					"methods" => array(
						array(
							$this,
							"verifyDeliveryModule"
						)
					)
				))
			)
		));
	}

	/**
	 *
	 * @param array $errors
	 * @param ExecutionContextInterface $context
	 */
	private function verifyState(Address $address, array &$errros)
	{
		$country = $address->getCountry();
		if (!$country->getHasStates()) {
			return;
		}

		$state = $address->getState();
		if (null !== $state) {
			if ($state->getCountryId() !== $country->getId()) {
				$errors[] = Translator::getInstance()->trans('This state doesn\'t belong to this country.');
			}
		}
		else {
			$errors[] = Translator::getInstance()->trans('You should select a state for this country.');
		}
	}

	/**
	 *
	 * @param array $errors
	 * @param ExecutionContextInterface $context
	 */
	private function verifyZipCode(Address $address, array &$errors)
	{
		$country = $address->getCountry();
		if (!$country->getNeedZipCode()) {
			return;
		}
		$zipCodeRegExp = $country->getZipCodeRE();
		if (null !== $zipCodeRegExp) {
			if (!preg_match($zipCodeRegExp, $address->getZipcode())) {
				$errors[] = Translator::getInstance()->trans('This zip code should respect the following format : %format.', [
					'%format' => $country->getZipCodeFormat()
				]);
			}
		}
	}

	public function verifyDeliveryAddress($value, ExecutionContextInterface $context)
	{
		$address = AddressQuery::create()->findPk($value);

		if (null === $address) {
			$context->addViolation(Translator::getInstance()->trans('Address ID not found'));
			return;
		}

		$disabledCountries = explode(' ', ConfigQuery::read('covid19_disabledcountries', ''));
		if (count($disabledCountries)) {
			$country = $address->getCountry();
			$countryIso = $country->getIsoalpha3();
			if (in_array($countryIso, $disabledCountries)) {
				$context->addViolation(Translator::getInstance()->trans('covid19_disabledcountries_error'));
				return;
			}
		}

		// Check address errors
		$addressErrors = array();
		$size1 = mb_strlen($address->getAddress1());
		$size2 = mb_strlen($address->getAddress2());
		if ($size1 > 32 || $size2 > 32) {
			$addressErrors[] = Translator::getInstance()->trans('order_delivery_address_error_linesize');
		}
		$this->verifyZipCode($address, $addressErrors);
		$this->verifyState($address, $addressErrors);
		if (empty($address->getPhone()) && empty($address->getCellphone())) {
			$addressErrors[] = Translator::getInstance()->trans('order_delivery_address_error_missingphone');
		}
		if (count($addressErrors)) {
			$error = Translator::getInstance()->trans('order_delivery_address_error_header') . '<br />';
			foreach ($addressErrors as $addressError) {
				$error .= sprintf('&nbsp; • %s<br />', $addressError);
			}
			$error .= Translator::getInstance()->trans('order_delivery_address_error_footer');
			$context->addViolation($error);
		}
	}

	public function verifyDeliveryModule($value, ExecutionContextInterface $context)
	{
		$module = ModuleQuery::create()->filterActivatedByTypeAndId(BaseModule::DELIVERY_MODULE_TYPE, $value)->findOne();

		if (null === $module) {
			$context->addViolation(Translator::getInstance()->trans('Delivery module ID not found'));
		}
		elseif (!$module->isDeliveryModule()) {
			$context->addViolation(sprintf(Translator::getInstance()->trans('delivery module %s is not a Thelia\Module\DeliveryModuleInterface'), $module->getCode()));
		}
	}

	public function getName()
	{
		return 'thelia_order_delivery';
	}
}
