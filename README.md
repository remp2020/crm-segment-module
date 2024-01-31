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


