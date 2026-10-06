<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DTR {{ $dtr['employee_number'] }} {{ $dtr['month'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 46px 56px 42px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.25;
            margin: 0;
        }

        h1 {
            font-size: 14px;
            margin: 0 0 26px;
            text-align: center;
        }

        .certification {
            font-size: 10px;
            line-height: 1.55;
            margin: 0 0 15px;
        }

        .filled-line,
        .blank-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            line-height: 1.1;
            text-align: center;
            vertical-align: baseline;
        }

        .filled-line {
            min-width: 112px;
            padding: 0 4px 2px;
        }

        .blank-line {
            min-width: 215px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        th,
        td {
            border: 0.65px solid #000;
            overflow: hidden;
            text-align: center;
            vertical-align: middle;
        }

        thead th {
            font-size: 8px;
            height: 27px;
            line-height: 1.1;
            padding: 2px;
        }

        tbody td {
            font-size: 7.6px;
            height: 13px;
            line-height: 1;
            padding: 1px 2px;
            white-space: nowrap;
        }

        .date-column { width: 24%; }
        .hours-column { width: 21%; }
        .rendered-column { width: 25%; }
        .remarks-column { width: 30%; }

        .prepared {
            font-size: 10px;
            margin-top: 22px;
        }

        .signature-space {
            height: 42px;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 3px;
            text-align: center;
            width: 285px;
        }

        .employee-name {
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <h1>WORK FROM HOME MONTHLY ATTENDANCE CERTIFICATION</h1>

    <p class="certification">
        I hereby certify that from
        <span class="filled-line">{{ $dtr['first_date_label'] }}</span>
        to
        <span class="filled-line">{{ $dtr['last_date_label'] }}</span>
        (see below detailed dates), I have actually rendered services to the Company for its various
        purposes/operations as authorized by my head,
        <span class="blank-line">&nbsp;</span>
        for at least 40 hours/week.
    </p>

    <table>
        <thead>
            <tr>
                <th class="date-column">DATE</th>
                <th class="hours-column">TOTAL HRS.<br>SPENT</th>
                <th class="rendered-column">ATTENDANCE<br>RENDERED</th>
                <th class="remarks-column">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dtr['rows'] as $row)
                <tr>
                    <td>{{ $row['date_label'] }}</td>
                    <td>{{ $row['total_hours'] }}</td>
                    <td>{{ $row['attendance_rendered'] }}</td>
                    <td>{{ $row['remarks'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="prepared">Prepared by:</div>
    <div class="signature-space"></div>
    <div class="signature-line">
        <div class="employee-name">{{ $dtr['employee_name'] }}</div>
        <div>signature over printed name of employee</div>
    </div>
</body>
</html>
