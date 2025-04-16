# CRM Segment Module

[![Translation status @ Weblate](https://hosted.weblate.org/widgets/remp-crm/-/segment-module/svg-badge.svg)](https://hosted.weblate.org/projects/remp-crm/segments-module/)

## Segment recalculation

Default segment recalculation times:

| periodicity | default recalculation time       |
|-------------|----------------------------------|
| minutes     | asap                             |
| hours       | at 30. minute of configured hour |
| days        | at 4:00 hour of configured day   |

You can configure default segment recalculation times by adding these setup method calls to your configuration file:

```neon
segmentRecalculationConfig:
    setup:
    	# sets time of the day when segments with daily periodicity are recalculated
        - setDailyRecalculationTime('4:00')
        # sets minute of the hour in which segments with hourly periodicity are recalculated
        - setHourlyRecalculationMinute('30')
```

### Segment nesting

Segment nesting is a feature, that adds ability to use one segment in other segment definition.  

This feature is disabled by default, since it's only supported by our default implementation of `SegmentInterface`. To enable it, add this to your neon configuration:

```neon
segments:
    segment_nesting: true
```

After enabling, new `SegmentCriteria` criteria is registered and available to use in visual Segments editor. 

#### Segments editor v1

The feature is also available in segments text editor. To reference other segment in a segment query, use the code `%segment.ACTUAL_SEGMENT_CODE%`. 

For example, let's have a segment `segment_a` specified by the query:

```sql
SELECT users.id, users.email FROM users WHERE id > 100 AND id < 120
```

With feature nesting enabled, we can define `segment_b` query like this:

```sql
SELECT * FROM users
WHERE users.id IN (SELECT id FROM (%segment.segment_a%) a)
```

During `segment_b` execution, placeholder `%segment.segment_a%` will be replaced by the actual `segment_a` query.

## Segment query forbidden tables

Forbidden tables are database tables that users are not allowed to reference when creating or updating segment queries.
If a query tries to use a forbidden table — even just reading from it (SELECT), joining it, or modifying it — the validator will reject the query.
This validation happens when the query is being created or edited, not at runtime execution.

You can configure forbidden tables through your configuration file:
```neon
segmentQueryValidator:
    setup:
        - addForbiddenTables('table_name')
```

## API documentation

All examples use `http://crm.press` as a base domain. Please change the host to the one you use
before executing the examples.

All examples use `XXX` as a default value for authorization token, please replace it with the
real tokens:

* *API tokens.* Standard API keys for server-server communication. It identifies the calling application as a whole.
  They can be generated in CRM Admin (`/api/api-tokens-admin/`) and each API key has to be whitelisted to access
  specific API endpoints. By default the API key has access to no endpoint.
* *User tokens.* Generated for each user during the login process, token identify single user when communicating between
  different parts of the system. The token can be read:
  * From `n_token` cookie if the user was logged in via CRM.
  * From the response of [`/api/v1/users/login` endpoint](https://github.com/remp2020/crm-users-module#post-apiv1userslogin) -
    you're free to store the response into your own cookie/local storage/session.

API responses can contain following HTTP codes:

| Value                     | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| 200 OK                    | Successful response, default value                                  |
| 400 Bad Request           | Invalid request (missing required parameters)                       |
| 403 Forbidden             | The authorization failed (provided token was not valid)             |
| 404 Not found             | Referenced resource wasn't found                                    |
| 500 Internal server error | Server related errors. You'll find more details in application log. |

If possible, the response includes `application/json` encoded payload with message explaining
the error further.

---

#### GET `/api/v1/segments/daily-count-stats`

Prints daily count of users/values in the segment with ability to filter by date range.

Endpoint requires `segment_code` to be provided.

##### *Headers:*

| Name          | Value           | Required | Description |
|---------------|-----------------|----------|-------------|
| Authorization | Bearer *String* | yes      | User token. |

##### *Params:*

| Name         | Value    | Required | Description                                          |
|--------------|----------|----------|------------------------------------------------------|
| segment_code | *String* | yes      | Code of the segment.                                 |
| date_from    | *String* | no       | Optional date 'from' (inclusive). Format: YYYY-MM-DD |
| date_to      | *String* | no       | Optional date 'to' (inclusive). Format: YYYY-MM-DD   |

##### *Examples:*

```shell
curl -X GET \
  http://crm.press/api/v1/segments/daily-count-stats?segment_code=all_users \
  -H 'Authorization: Bearer XXX'
```

```shell
curl -X GET \
  http://crm.press/api/v1/segments/daily-count-stats?segment_code=all_users&date_from=2023-12-25 \
  -H 'Authorization: Bearer XXX'
```

Response:

```json5
[
  {
    "date": "2024-03-24",
    "count": 299
  },
  {
    "date": "2024-03-25",
    "count": 300
  }
]
```

## Segments Size Overview Graph

This extension contains a stacked graph that displays an overview of the segment sizes. This graph needs to be registered and reused as a simple widget with the corresponding segment codes you want to display. Segments should ideally be exclusive, as the graph displays the sum of their sizes.

For example, when you have registered a simple widget placeholder like this:

```latte
{control simpleWidget 'admin.subscriptions.dashboard.content'}
```

Then, you can register the graph widget, for example, in your `SegmentModule` like this:

```php
<?php

class SegmentModule extends CrmModule
{
    // ...
    
    public function registerLazyWidgets(LazyWidgetManagerInterface $lazyWidgetManager)
    {
        /** @var SegmentsSizeOverviewStackedGraphWidgetFactory $segmentsSizeOverviewStackedGraphWidgetFactory */
        $segmentsSizeOverviewStackedGraphWidgetFactory = $this->getInstance(SegmentsSizeOverviewStackedGraphWidgetFactory::class);

        $lazyWidgetManager->registerWidgetWithInstance(
            'admin.subscriptions.dashboard.content',
            $segmentsSizeOverviewStackedGraphWidgetFactory->create()->setSegmentCodes(['all_users']),
        );
    }

    // ...
```
