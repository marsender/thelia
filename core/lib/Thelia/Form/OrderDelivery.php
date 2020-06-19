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
/*************************************************************************************/

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class OrderDelivery
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderDelivery extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("delivery-address", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyDeliveryAddress"),
                        ),
                    )),
                ),
            ))
            ->add("delivery-module", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyDeliveryModule"),
                        ),
                    )),
                ),
            ));
    }

    public function verifyDeliveryAddress($value, ExecutionContextInterface $context)
    {
        $address = AddressQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans("Address ID not found"));
            return;
        }
        if (empty($address->getPhone()) && empty($address->getCellphone())) {
        	$context->addViolation('missingphone_error');
        }
        $size1 = mb_strlen($address->getAddress1());
        $size2 = mb_strlen($address->getAddress2());
        if ($size1 > 32 || $size2 > 32) {
        	$context->addViolation('address_size_error');
        }
        $disabledCountries = explode(' ', ConfigQuery::read('covid19_disabledcountries', ''));
        if (count($disabledCountries)) {
	        $country = $address->getCountry();
	        $countryIso = $country->getIsoalpha3();
	        //if ($countryIso == 'FRA') {
	        //	if (empty($address->getPhone()) && empty($address->getCellphone())) {
	        //		$context->addViolation('covid19_missingphone_error');
	        //	}
	        //}
	        if (in_array($countryIso, $disabledCountries)) {
	        	$context->addViolation('covid19_disabledcountries_error');
	        }
        }
    }

    public function verifyDeliveryModule($value, ExecutionContextInterface $context)
    {
        $module = ModuleQuery::create()
            ->filterActivatedByTypeAndId(BaseModule::DELIVERY_MODULE_TYPE, $value)
            ->findOne();

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans("Delivery module ID not found"));
        } elseif (! $module->isDeliveryModule()) {
            $context->addViolation(
                sprintf(Translator::getInstance()->trans("delivery module %s is not a Thelia\Module\DeliveryModuleInterface"), $module->getCode())
            );
        }
    }

    public function getName()
    {
        return "thelia_order_delivery";
    }
}
