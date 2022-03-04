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


