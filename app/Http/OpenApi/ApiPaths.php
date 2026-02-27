<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

/**
 * OpenAPI path definitions. Base path is /api.
 *
 * @SWG\Path(path="/landlord/plans",
 *   get=@SWG\Get(tags={"Landlord - Plans"}, summary="List active plans", description="Returns all active billing plans. Public. Central domain.", @SWG\Response(response=200, description="List of plans")),
 *   post=@SWG\Post(tags={"Landlord - Plans"}, summary="Create plan", security={{"bearerAuth":{}}}, @SWG\Parameter(in="body", name="body", required=true, @SWG\Schema(ref="#/definitions/CreatePlanRequest")), @SWG\Response(response=200, description="Created plan"), @SWG\Response(response=400, description="Bad request"), @SWG\Response(response=401, description="Unauthorized"))
 * )
 * @SWG\Path(path="/landlord/plans/{id}/activate", post=@SWG\Post(tags={"Landlord - Plans"}, summary="Activate plan", security={{"bearerAuth":{}}}, @SWG\Parameter(in="path", name="id", type="string", required=true), @SWG\Response(response=200, description="Plan activated"), @SWG\Response(response=400, description="Bad request"), @SWG\Response(response=401, description="Unauthorized")))
 * @SWG\Path(path="/landlord/plans/{id}/deactivate", post=@SWG\Post(tags={"Landlord - Plans"}, summary="Deactivate plan", security={{"bearerAuth":{}}}, @SWG\Parameter(in="path", name="id", type="string", required=true), @SWG\Response(response=200, description="Plan deactivated"), @SWG\Response(response=400, description="Bad request"), @SWG\Response(response=401, description="Unauthorized")))
 * @SWG\Path(path="/landlord/subscriptions/subscribe", post=@SWG\Post(tags={"Landlord - Subscriptions"}, summary="Subscribe tenant to plan", security={{"bearerAuth":{}}}, @SWG\Parameter(in="body", name="body", required=true, @SWG\Schema(type="object", required={"tenant_id","plan_id"}, @SWG\Property(property="tenant_id", type="string"), @SWG\Property(property="plan_id", type="string"))), @SWG\Response(response=200, description="Subscription created"), @SWG\Response(response=400, description="Bad request"), @SWG\Response(response=401, description="Unauthorized")))
 * @SWG\Path(path="/landlord/subscriptions/cancel", post=@SWG\Post(tags={"Landlord - Subscriptions"}, summary="Cancel subscription", security={{"bearerAuth":{}}}, @SWG\Parameter(in="body", name="body", required=true, @SWG\Schema(type="object", required={"tenant_id"}, @SWG\Property(property="tenant_id", type="string"))), @SWG\Response(response=200, description="Subscription cancelled"), @SWG\Response(response=401, description="Unauthorized")))
 * @SWG\Path(path="/landlord/subscriptions/{tenantId}", get=@SWG\Get(tags={"Landlord - Subscriptions"}, summary="Get tenant subscription", security={{"bearerAuth":{}}}, @SWG\Parameter(in="path", name="tenantId", type="string", required=true), @SWG\Response(response=200, description="Subscription details"), @SWG\Response(response=404, description="Not found"), @SWG\Response(response=401, description="Unauthorized")))
 * @SWG\Path(path="/landlord/billing/checkout/{plan}", post=@SWG\Post(tags={"Landlord - Billing"}, summary="Create billing checkout session", description="Stripe Checkout session. Central domain.", security={{"bearerAuth":{}}}, @SWG\Parameter(in="path", name="plan", type="string", required=true), @SWG\Response(response=200, description="Checkout session URL or session id"), @SWG\Response(response=400, description="Bad request"), @SWG\Response(response=401, description="Unauthorized")))
 * @SWG\Path(path="/landlord/billing/webhook", post=@SWG\Post(tags={"Landlord - Billing"}, summary="Stripe webhook", description="No auth; verify signature.", @SWG\Response(response=200, description="Processed"), @SWG\Response(response=400, description="Bad request")))
 * @SWG\Path(path="/landlord/billing/success", get=@SWG\Get(tags={"Landlord - Billing"}, summary="Billing checkout success redirect", @SWG\Response(response=302, description="Redirect after successful checkout")))
 * @SWG\Path(path="/landlord/billing/cancel", get=@SWG\Get(tags={"Landlord - Billing"}, summary="Billing checkout cancel redirect", @SWG\Response(response=302, description="Redirect when checkout cancelled")))
 * @SWG\Path(path="/landlord/billing/portal/return", get=@SWG\Get(tags={"Landlord - Billing"}, summary="Return from customer portal", @SWG\Response(response=302, description="Redirect after portal")))
 */
class ApiPathsLandlord
{
}
