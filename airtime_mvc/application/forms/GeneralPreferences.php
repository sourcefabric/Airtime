<?php

class Application_Form_GeneralPreferences extends Zend_Form_SubForm
{

    public function init()
    {

        $notEmptyValidator = Application_Form_Helper_ValidationTypes::overrideNotEmptyValidator();
        $this->setDecorators(array(
            array('ViewScript', array('viewScript' => 'form/preferences_general.phtml'))
        ));

        $defaultFade = Application_Model_Preference::GetDefaultFade();
        if ($defaultFade == "") {
            $defaultFade = '0.5';
        }

        //Station name
        $this->addElement('text', 'stationName', array(
            'class'      => 'input_text',
            'label'      => _('Station Name'),
            'required'   => false,
            'filters'    => array('StringTrim'),
            'value' => Application_Model_Preference::GetStationName(),
            'decorators' => array(
                'ViewHelper'
            )
        ));

        //Default station fade
        $this->addElement('text', 'stationDefaultFade', array(
            'class'      => 'input_text',
            'label'      => _('Default Fade (s):'),
            'required'   => true,
            'filters'    => array('StringTrim'),
            'validators' => array(array($notEmptyValidator, 'regex', false,
                array('/^[0-9]{1,2}(\.\d{1})?$/',
                'messages' => _('enter a time in seconds 0{.0}')))),
            'value' => $defaultFade,
            'decorators' => array(
                'ViewHelper'
            )
        ));

        $third_party_api = new Zend_Form_Element_Radio('thirdPartyApi');
        $third_party_api->setLabel(
            sprintf(_('Allow Remote Websites To Access "Schedule" Info?%s (Enable this to make front-end widgets work.)'), '<br>'));
        $third_party_api->setMultiOptions(array(_("Disabled"),
                                            _("Enabled")));
        $third_party_api->setValue(Application_Model_Preference::GetAllow3rdPartyApi());
        $third_party_api->setDecorators(array('ViewHelper'));
        $this->addElement($third_party_api);
        
        $locale = new Zend_Form_Element_Select("locale");
        $locale->setLabel(_("Language"));
        $locale->setMultiOptions(Application_Model_Locale::getLocales());
        $locale->setValue(Application_Model_Preference::GetLocale());
        $locale->setDecorators(array('ViewHelper'));
        $this->addElement($locale);

        /* Form Element for setting the Timezone */
        $timezone = new Zend_Form_Element_Select("timezone");
        $timezone->setLabel(_("Timezone"));
        $timezone->setMultiOptions($this->getTimezones());
        $timezone->setValue(Application_Model_Preference::GetTimezone());
        $timezone->setDecorators(array('ViewHelper'));
        $this->addElement($timezone);

        /* Form Element for setting which day is the start of the week */
        $week_start_day = new Zend_Form_Element_Select("weekStartDay");
        $week_start_day->setLabel(_("Week Starts On"));
        $week_start_day->setMultiOptions($this->getWeekStartDays());
        $week_start_day->setValue(Application_Model_Preference::GetWeekStartDay());
        $week_start_day->setDecorators(array('ViewHelper'));
        $this->addElement($week_start_day);
    }

    private function getTimezones()
    {
        $regions = array(
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Arctic' => DateTimeZone::ARCTIC,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC
        );

        $tzlist = array();

        foreach ($regions as $name => $mask) {
            $ids = DateTimeZone::listIdentifiers($mask);
            foreach ($ids as $id) {
                $tzlist[$id] = str_replace("_", " ", $id);
            }
        }

        return $tzlist;
    }

    private function getWeekStartDays()
    {
        $days = array(
            _('Sunday'),
            _('Monday'),
            _('Tuesday'),
            _('Wednesday'),
            _('Thursday'),
            _('Friday'),
            _('Saturday')
        );

        return $days;
    }
}
