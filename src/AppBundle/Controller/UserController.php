<?php
/**
 * @author Ylambers <yaron.lambers@commercetools.de>
 */

namespace Commercetools\Sunrise\AppBundle\Controller;


use Commercetools\Core\Client;
use Commercetools\Core\Model\Common\Address;
use Commercetools\Core\Model\Common\Money;
use Commercetools\Core\Model\Order\Order;
use Commercetools\Core\Request\Customers\Command\CustomerChangeAddressAction;
use Commercetools\Core\Request\Customers\CustomerByIdGetRequest;
use Commercetools\Core\Request\Customers\CustomerUpdateRequest;
use Commercetools\Sunrise\AppBundle\Entity\UserAddress;
use Commercetools\Sunrise\AppBundle\Model\ViewData;
use Commercetools\Sunrise\AppBundle\Model\ViewDataCollection;
use Commercetools\Sunrise\AppBundle\Security\User\CTPUser;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserController extends SunriseController
{
    public function editAddressAction(Request $request)
    {
        $customer = $this->getCustomer($this->getUser());
        $address = $customer->getDefaultShippingAddress();

        $userAddress = UserAddress::ofAddress($address);

        $form = $this->createFormBuilder($userAddress)
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('streetName', TextType::class)
            ->add('StreetNumber', TextType::class)
            ->add('PostalCode', TextType::class)
            ->add('City', TextType::class)
            ->add('Region', TextType::class)
            ->add('Country', TextType::class)
            ->add('Company', TextType::class)
            ->add('Phone', TextType::class)
            ->add('Email', TextType::class)
            ->add('title', TextType::class)
            ->add('save', SubmitType::class, array('label' => 'Save user'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            /**
             * @var UserAddress $formAddress
             */
            $formAddress = $form->getData();
            $newAddress = $formAddress->toCTPAddress();

            $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion());
            $request->addAction(CustomerChangeAddressAction::ofAddressIdAndAddress($address->getId(), $newAddress));

            /**
             * @var Client $client
             */
            $client = $this->get('commercetools.client');
            $response = $request->executeWithClient($client);

            $newCustomer = $request->mapResponse($response);

            return new response('User has bin updated!');
        }

        return $this->render('editAddress.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function loginAction(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return new RedirectResponse($this->generateUrl('myAccount'));
        }
        $viewData = $this->getViewData('MyAccount - Login', $request);
        $authUtils = $this->get('security.authentication_utils');
        // get the login error if there is one
        $error = $authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authUtils->getLastUsername();

        return $this->render('my-account-login.hbs', $viewData->toArray());
    }

    public function secretAction(Request $request)
    {
        return new Response('Top secret');
    }

    public function detailsAction(Request $request)
    {

        $viewData = $this->getViewData('MyAccount - Details', $request);

        $customer = $this->getCustomer($this->getUser());
        $address = $customer->getDefaultShippingAddress();

        $viewData->content->personalDetails = new ViewData();
        $viewData->content->personalDetails->name = $customer->getFirstName() . ' ' . $customer->getLastName();
        $viewData->content->personalDetails->name = $address->getFirstName() . ' ' . $address->getLastName();


        return $this->render('my-account-personal-details.hbs', $viewData->toArray());
    }

    public function addressesAction(Request $request)
    {
        $viewData = $this->getViewData('MyAccount - Details');

        $customer = $this->getCustomer($this->getUser());

        $viewData->content->shippingAddress = $this->getViewAddress($customer->getDefaultShippingAddress());
        $viewData->content->billingAddress = $this->getViewAddress($customer->getDefaultBillingAddress());;

        return $this->render('my-account-address-book.hbs', $viewData->toArray());

    }

    protected function getViewAddress(Address $address)
    {
        $viewAddress = new ViewData();

        $viewAddress->name = $address->getFirstName() . ' ' . $address->getLastName();
        $viewAddress->address = $address->getCity() . ' ' . $address->getPostalCode();
        $viewAddress->address = $address->getStreetName() . ' ' . $address->getStreetNumber() . ' ';
        $viewAddress->postalCode = $address->getPostalCode() . ' ' . $address->getCity();
        $viewAddress->country = $address->getCountry();
        $viewAddress->compay = $address->getCompany();

        return $viewAddress;
    }
    
    public function ordersAction(Request $request)
    {
        $viewData = $this->getViewData('MyAccount - Orders');
        $orders = $this->get('app.repository.order')->getOrders($this->getUser()->getId());


        $viewData->content->orderNumberTitle = $this->trans('my-account:orderNumber');
        /**
         * @var Order $order
         */
        $viewData->content->order = new ViewDataCollection();
        foreach ($orders as $order) {

            $orderData = new ViewData();
            $order->orderNumber = $order->getOrderNumber();
            $orderData->date = $order->getCreatedAt()->format('d.m.Y');
            $orderData->total = $order->getTotalPrice();
            $orderData->paymentStatus = $order->getPaymentState();
            $orderData->shipping = $order->getShipmentState();
            $orderData->view = 'VIEW';
            $orderData->detailUri = $this->generateUrl('myOrderDetails', ['orderId' => $order->getId()]);

            $viewData->content->order->add($orderData);

        }

        return $this->render('my-account-my-orders.hbs', $viewData->toArray());
    }


    public function orderDetailAction(Request $request, $orderId)
    {
        // @todo change every title, now it is hardcoded

        $viewData = $this->getViewData('MyAccount - Orders');
        /**
         * @var Order $order
         */
        $order = $this->get('app.repository.order')->getOrder($orderId);


        if ($order->getCustomerId() !== $this->getUser()->getId()) {
            throw new AccessDeniedException();
        }

        $this->addOrderDetails($viewData->content, $order);

        return $this->render('my-account-my-orders-order.hbs', $viewData->toArray());
    }

    protected function addOrderDetails($content, Order $order)
    {
        $content->yourOrderTitle = $this->trans('Your Order Details');
        $content->orderNumberTitle = $this->trans('Order number');
        $content->orderNumber = $order->getOrderNumber();
        $content->orderDateTitle = $this->trans('Order date');
        $content->orderDate = $order->getCreatedAt()->format('d.m.Y');
        $content->printReceiptBtn = $this->trans('print receipt');

        $shippingAddress = $order->getShippingAddress();
        $shippingAddressData = new ViewData();

        $shippingAddressData->title = $this->trans('shipping details');
        $shippingAddressData->name = $shippingAddress->getFirstName(). ' ' . $shippingAddress->getLastName();
        $shippingAddressData->address = $shippingAddress->getStreetName() . ' ' . $shippingAddress->getStreetNumber();
        $shippingAddressData->city = $shippingAddress->getCity();
        $shippingAddressData->region = $shippingAddress->getRegion();
        $shippingAddressData->postalCode = $shippingAddress->getPostalCode();
        $shippingAddressData->country = $shippingAddress->getCountry();
        $shippingAddressData->number = $shippingAddress->getPhone();
        $shippingAddressData->email = $shippingAddress->getEmail();

        $content->shippingAddress = $shippingAddressData;


        $billingAddress = $order->getBillingAddress();
        $billingAddressData = new ViewData();

        //@todo change the name to shippingaddress or leave it like billing address
        $billingAddressData->title = $this->trans('billing address');
        $billingAddressData->name = $billingAddress->getFirstName(). ' ' . $billingAddress->getLastName();
        $billingAddressData->address = $billingAddress->getStreetName(). ' ' . $billingAddress->getStreetNumber();
        $billingAddressData->city = $billingAddress->getCity();
        $billingAddressData->region = $billingAddress->getRegion();
        $billingAddressData->postalCode = $billingAddress->getPostalCode();
        $billingAddressData->country = $billingAddress->getCountry();
        $billingAddressData->number = $billingAddress->getPhone();
        $billingAddressData->email = $billingAddress->getEmail();

        $content->billingAddress = $billingAddressData;

        $shippingMethod = $order->getShippingInfo();
        $shippingMethodData = new ViewData();
        $shippingMethodData->title = $this->trans('Shipping Method');
        if (!is_null($shippingMethod)) {
            $shippingMethodData->text = $this->trans($shippingMethod->getShippingMethodName(), [], 'orders');
            $content->shippingMethod = $shippingMethodData;
        }

        //@todo activate PAYMENT DETAILS, NOTE: not working properly
//        $paymentDetails = $order->getPaymentState();
//        $paymentDetailsData = new ViewData();
//
//        $paymentDetailsData->title = $this->trans('Payment Details');
//        $viewData->content->paymentDetails = $paymentDetails;

        //@todo check if the prices are right!!
        $content->Code = $order->getDiscountCodes()->toArray();
        $content->subtotal = $order->getTaxedPrice()->getTotalNet();

        $content->orderDiscountTitle = $this->trans('Order discount');
        $content->standartDeliveryTitle = $this->trans('Standard Delivery');

        //@todo prices of orderdiscount and standartdelivery are not right yet

        $content->promoCode = $order->getDiscountCodes();

//        $viewData->content->orderDiscount = $order->getTotalPrice();

        $content->salesTaxTitle = $this->trans('Sales Tax');
        $content->orderTotalTitle = $this->trans('Order Total');
        if ($order->getShippingInfo()) {
            $content->standartDelivery = $order->getShippingInfo()->getPrice();
        }
        $content->orderTotal = $order->getTotalPrice();
        $content->salesTax = Money::ofCurrencyAndAmount(
            $order->getTaxedPrice()->getTotalGross()->getCurrencyCode(),
            $order->getTaxedPrice()->getTotalGross()->getCentAmount()
            - $order->getTaxedPrice()->getTotalNet()->getCentAmount()
        );

        $lineItems = $order->getLineItems();

        $content->order = new ViewDataCollection();
        foreach ($lineItems as $lineItem) {
            $variant = $lineItem->getVariant();

            $lineItemsData = new ViewData();
            $lineItemsData->totalPrice = (string)$lineItem->getTotalPrice();
            $lineItemsData->image = $variant->getImages()->current()->getUrl();
            $lineItemsData->quantity = (string)$lineItem->getQuantity();
            $price = $lineItem->getPrice();
            // @todo change to discountedPricePerQuantity the price is not right
            if (!is_null($price->getDiscounted())) {
                $lineItemsData->discountedPrice = (string)$price->getDiscounted()->getValue();
            }
            $lineItemsData->price = (string)$price->getValue();
            $lineItemsData->productTitleOne = $lineItem->getName();
            $lineItemsData->sku = $lineItem->getVariant()->getSku();
            $content->order->add($lineItemsData);
        }
        $content->productDescriptionTitle = $this->trans('Product Description');
    }

    protected function getCustomer(CTPUser $user)
    {
        if (!$user instanceof CTPUser) {
            throw new \InvalidArgumentException();
        }

        /**
         * @var Client $client
         */
        $client = $this->get('commercetools.client');

        $request = CustomerByIdGetRequest::ofId($user->getId());

        $response = $request->executeWithClient($client);
        $customer = $request->mapResponse($response);

        return $customer;
    }
}
