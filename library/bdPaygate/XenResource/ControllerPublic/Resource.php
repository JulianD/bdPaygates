<?php

class bdPaygate_XenResource_ControllerPublic_Resource extends XFCP_bdPaygate_XenResource_ControllerPublic_Resource
{
	public function actionBuyers()
	{
		list($resource, $category) = $this->_getResourceViewInfo();

		if (empty($resource['is_fileless']) AND !empty($resource['cost']) AND $resource['user_id'] == XenForo_Visitor::getUserId())
		{
			// good
		}
		else
		{
			return $this->responseNoPermission();
		}

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('resources/buyers', $resource));

		$purchaseModel = $this->getModelFromCache('bdPaygate_Model_Purchase');
		$buyers = $purchaseModel->getUsersWhoPurchased('resource', $resource['resource_id']);

		$viewParams = array(
			'resource' => $this->_getResourceModel()->prepareResource($resource),
			'category' => $category,

			'buyers' => $buyers,
		);

		return $this->_getResourceViewWrapper('buyers', $resource, $category, $this->responseView('bdPaygate_ViewPublic_Resource_Buyers', 'bdpaygate_resource_buyers', $viewParams));
	}

	public function actionAddBuyer()
	{
		list($resource, $category) = $this->_getResourceViewInfo();

		if (empty($resource['is_fileless']) AND !empty($resource['cost']) AND $resource['user_id'] == XenForo_Visitor::getUserId())
		{
			// good
		}
		else
		{
			return $this->responseNoPermission();
		}

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('resources/add-buyer', $resource));

		if ($this->isConfirmedPost())
		{
			$usernames = $this->_input->filterSingle('usernames', XenForo_Input::STRING);
			$usernames = explode(',', $usernames);

			$purchaseModel = $this->getModelFromCache('bdPaygate_Model_Purchase');

			$users = $this->getModelFromCache('XenForo_Model_User')->getUsersByNames($usernames);

			if (empty($users))
			{
				throw new XenForo_Exception(new XenForo_Phrase('requested_user_not_found'), true);
			}

			foreach ($users as $user)
			{
				$purchased = $purchaseModel->getPurchaseByContentAndUser('resource', $resource['resource_id'], $user['user_id']);

				if (empty($purchased))
				{
					$purchaseModel->createRecord('resource', $resource['resource_id'], $user['user_id']);
				}
			}

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildPublicLink('resources/buyers', $resource));
		}
		else
		{
			$viewParams = array(
				'resource' => $this->_getResourceModel()->prepareResource($resource),
				'category' => $category,
			);

			return $this->_getResourceViewWrapper('buyers', $resource, $category, $this->responseView('bdPaygate_ViewPublic_Resource_AddBuyer', 'bdpaygate_resource_add_buyer', $viewParams));
		}
	}

	public function actionDeleteBuyer()
	{
		list($resource, $category) = $this->_getResourceViewInfo();

		if (empty($resource['is_fileless']) AND !empty($resource['cost']) AND $resource['user_id'] == XenForo_Visitor::getUserId())
		{
			// good
		}
		else
		{
			return $this->responseNoPermission();
		}

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('resources/delete-buyer', $resource));

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($userId);
		if (empty($user))
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
		}

		if ($this->isConfirmedPost())
		{
			$this->getModelFromCache('bdPaygate_Model_Purchase')->deleteRecords('resource', $resource['resource_id'], $user['user_id']);

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildPublicLink('resources/buyers', $resource));
		}
		else
		{
			$viewParams = array(
				'resource' => $this->_getResourceModel()->prepareResource($resource),
				'category' => $category,
				'user' => $user,
			);

			return $this->_getResourceViewWrapper('buyers', $resource, $category, $this->responseView('bdPaygate_ViewPublic_Resource_DeleteBuyer', 'bdpaygate_resource_delete_buyer', $viewParams));
		}
	}

	public function actionPurchase()
	{
		list($resource, $category) = $this->_getResourceViewInfo();

		if (!$this->_getResourceModel()->bdPaygate_mustPurchaseToDownload($resource) OR !$this->_getResourceModel()->bdPaygate_canPurchaseResource($resource, $category, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		/* @var $processorModel bdPaygate_Model_Processor */
		$processorModel = $this->getModelFromCache('bdPaygate_Model_Processor');

		$processorNames = $processorModel->getProcessorNames();
		$processors = array();
		foreach ($processorNames as $processorId => $processorClass)
		{
			$processors[$processorId] = bdPaygate_Processor_Abstract::create($processorClass);
		}

		$viewParams = array(
			'resource' => $this->_getResourceModel()->prepareResource($resource),
			'category' => $category,

			'processors' => $processors,
		);

		return $this->responseView('bdPaygate_ViewPublic_Resource_Purchase', 'bdpaygate_resource_purchase', $viewParams);
	}

	public function actionPurchaseComplete()
	{
		return $this->responseMessage(new XenForo_Phrase('bdpaygate_purchase_resource_complete', array('purchased_link' => XenForo_Link::buildPublicLink('resources/purchased'), )));
	}

	public function actionPurchased()
	{
		/* @var $purchaseModel bdPaygate_Model_Purchase */
		$purchaseModel = $this->getModelFromCache('bdPaygate_Model_Purchase');

		$visitor = XenForo_Visitor::getInstance();

		$purchases = $purchaseModel->getPurchases(array(
			'content_type' => 'resource',
			'user_id' => $visitor['user_id'],
		));

		if (empty($purchases))
		{
			return $this->responseMessage(new XenForo_Phrase('bdpaygate_you_have_not_purchased_resources'));
		}

		$resourceIds = array();
		foreach ($purchases as $purchase)
		{
			$resourceIds[] = $purchase['content_id'];
		}
		$resources = $this->_getResourceModel()->getResourcesByIds($resourceIds);

		$viewParams = array('resources' => $this->_getResourceModel()->prepareResources($resources), );

		return $this->responseView('bdPaygate_ViewPublic_Resource_Purchased', 'bdpaygate_resource_purchased', $viewParams);
	}

	public function actionSave()
	{
		$GLOBALS[bdPaygate_Constant::GLOBALS_XFRM_CONTROLLERPUBLIC_RESOURCE_SAVE] = $this;

		return parent::actionSave();
	}

	public function bdPaygate_actionSave(XenResource_DataWriter_Resource $dw)
	{
		$input = $this->_input->filter(array(
			'bdpaygate_price' => XenForo_Input::UNUM,
			'bdpaygate_currency' => XenForo_Input::STRING,
			'file_hash' => XenForo_Input::STRING,
		));

		if (!empty($input['file_hash']))
		{
			$dw->getVersionDw()->setExtraData(XenResource_DataWriter_Version::DATA_ATTACHMENT_HASH, $input['file_hash']);
		}

		if (!empty($input['bdpaygate_price']) OR !empty($input['bdpaygate_currency']))
		{
			$dw->set('price', $input['bdpaygate_price']);
			$dw->set('currency', $input['bdpaygate_currency']);
		}

		unset($GLOBALS[bdPaygate_Constant::GLOBALS_XFRM_CONTROLLERPUBLIC_RESOURCE_SAVE]);
	}

	protected function _checkCsrf($action)
	{
		if (strtolower($action) == 'purchasecomplete')
		{
			// may be coming from external payment gateway
			return;
		}

		return parent::_checkCsrf($action);
	}

	protected function _getResourceAddOrEditResponse(array $resource, array $category, array $attachments = array())
	{
		$response = parent::_getResourceAddOrEditResponse($resource, $category, $attachments);

		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$params = &$response->params;

			if (!empty($params['category']['bdpaygate_allow_commercial_local']))
			{
				$params['allowLocal'] = true;

				if (empty($params['resource']['resource_id']))
				{
					$params['resourceType'] = 'local';
				}
			}

			$params['bdPaygate_currencies'] = $this->getModelFromCache('bdPaygate_Model_Processor')->getEnabledCurrencies();
		}

		return $response;
	}

}
