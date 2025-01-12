<?php
namespace IDangerous\NetgsmIYS\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DownloadSample extends Field
{
    protected $_template = 'IDangerous_NetgsmIYS::system/config/download_sample.phtml';

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'download_sample',
            'label' => __('Download Sample File'),
        ]);

        return $button->toHtml();
    }

    public function getDownloadUrl()
    {
        return $this->getUrl('idangerous_iys/import/downloadsample');
    }
}