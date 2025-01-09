<?php
namespace IDangerous\NetgsmIYS\Controller\Adminhtml\Records;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use IDangerous\NetgsmIYS\Model\IysDataFactory;

class View extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var IysDataFactory
     */
    protected $iysDataFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param IysDataFactory $iysDataFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        IysDataFactory $iysDataFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->iysDataFactory = $iysDataFactory;
    }

    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_NetgsmIYS::records');
    }

    /**
     * View action
     *
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->iysDataFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This record no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('IDangerous_NetgsmIYS::records');
        $resultPage->getConfig()->getTitle()->prepend(__('IYS Record Details'));

        return $resultPage;
    }
}