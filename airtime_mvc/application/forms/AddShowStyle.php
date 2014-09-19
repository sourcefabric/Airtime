<?php

require_once 'customfilters/ImageSize.php';

class Application_Form_AddShowStyle extends Zend_Form_SubForm
{

    public function init()
    {
       // Add show background-color input
        $this->addElement('text', 'add_show_background_color', array(
            'label'      => _('Background Colour:'),
            'class'      => 'input_text',
            'filters'    => array('StringTrim')
        ));

        $bg = $this->getElement('add_show_background_color');

        $bg->setDecorators(array(array('ViewScript', array(
            'viewScript' => 'form/add-show-style.phtml',
            'class'      => 'big'
        ))));

        $stringLengthValidator = Application_Form_Helper_ValidationTypes::overrideStringLengthValidator(6, 6);
        $bg->setValidators(array(
            'Hex', $stringLengthValidator
        ));

    	// Add show color input
        $this->addElement('text', 'add_show_color', array(
            'label'      => _('Text Colour:'),
            'class'      => 'input_text',
            'filters'    => array('StringTrim')
        ));

        $c = $this->getElement('add_show_color');

        $c->setDecorators(array(array('ViewScript', array(
            'viewScript' => 'form/add-show-style.phtml',
            'class'      => 'big'
        ))));

        $c->setValidators(array(
                'Hex', $stringLengthValidator
        ));
        
        // Show the current logo
        $this->addElement('image', 'add_show_logo_current', array(
        		'label'	=> _('Current Logo:'),
        ));
        
        $logo = $this->getElement('add_show_logo_current');
        $logo->setDecorators(array(
        	array('ViewScript', array(
        		'viewScript' => 'form/add-show-style.phtml',
        		'class'      => 'big'
        	))
        ));
        // Since we need to use a Zend_Form_Element_Image proto, disable it
        $logo->setAttrib('disabled','disabled');
        
        // Button to remove the current logo
        $this->addElement('button', 'add_show_logo_current_remove', array(
        		'label'	 => '<span class="ui-button-text">' . _('Remove') . '</span>',
        		'class'  => 'ui-button ui-state-default ui-button-text-only',
        		'escape' => false
        ));
        
        // Add show image input
        $upload = new Zend_Form_Element_File('add_show_logo');
        
        $upload->setLabel(_('Show Logo:'))
        	   ->setRequired(false)
        	   ->setDecorators(array('File', array('ViewScript', array(
        				'viewScript' => 'form/add-show-style.phtml',
        				'class'		 => 'big',
        	   			'placement'  => false
        		))))
               ->addValidator('Count', false, 1)
               ->addValidator('Extension', false, 'jpg,jpeg,png,gif')
        	   ->addFilter('ImageSize');
        	   
        $this->addElement($upload);
        
        // Add image preview
        $this->addElement('image', 'add_show_logo_preview', array(
        	'label'	=> _('Logo Preview:'),
        ));
        
        $preview = $this->getElement('add_show_logo_preview');
        $preview->setDecorators(array(array('ViewScript', array(
        		'viewScript' => 'form/add-show-style.phtml',
        		'class'      => 'big'
        ))));
        $preview->setAttrib('disabled','disabled');
    }

    public function disable()
    {
        $elements = $this->getElements();
        foreach ($elements as $element) {
            if ($element->getType() != 'Zend_Form_Element_Hidden') {
                $element->setAttrib('disabled','disabled');
            }
        }
    }

}
