<?php
namespace IDangerous\NetgsmIYS\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Import extends Field
{
    /**
     * @var string
     */
    protected $_template = 'IDangerous_NetgsmIYS::system/config/import.phtml';

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate import button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'import_button',
            'label' => __('Import'),
        ]);

        return $button->toHtml();
    }

    /**
     * Get import URL
     *
     * @return string
     */
    public function getImportUrl()
    {
        return $this->getUrl('netgsm_iys/import/process');
    }
}