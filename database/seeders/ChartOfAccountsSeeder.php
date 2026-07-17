<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Structure: groups with their children.
        // [code, ref, name] for the group, then a list of [code, ref, name] children.
        $income = [
            ['4100','A1','General Offering', [
                ['4101','A1-i','Executive (English) Service'],
                ['4102','A1-ii','Divine (Twi) Service'],
                ['4103','A1-iii','Joint Service'],
                ['4104','A1-iv','Bible Studies — Tuesday'],
                ['4105','A1-v','Miracle Service — Friday'],
                ['4106','A1-vi','Fundraisings'],
            ]],
            ['4200','A2','Tithe', []],
            ['4300','A3','Harvest Proceeds', []],
            ['4400','A4','Department Fund (Ministry)', [
                ['4401','A4-i','Men Ministry'],
                ['4402','A4-ii','Women Ministry'],
                ['4403','A4-iii','Children Ministry'],
                ['4404','A4-iv','Sunday School'],
                ['4405','A4-v','Funeral Dept.'],
                ['4406','A4-vi','Christ Ambassador (CA)'],
                ['4407','A4-vii','Welfare Dept.'],
                ['4408','A4-viii','Prayer Mtg (Wednesday)'],
            ]],
            ['4500','A5','Project Offering', []],
            ['4600','A6','Seed Sowing', []],
            ['4700','A7','Other Income', [
                ['4701','A7-i','Dist/Reg/Gen. Council'],
                ['4702','A7-ii','Fund Raising'],
                ['4703','A7-iii','Child Dedication'],
                ['4704','A7-iv','All Night'],
                ['4705','A7-v','Satellite Churches'],
                ['4706','A7-vi','Revival/Retreat/Seminars'],
                ['4707','A7-vii','Scholarship Fund'],
                ['4708','A7-viii','Book Sales (Sunday School)'],
                ['4709','A7-ix','Missions'],
                ['4710','A7-x','Joy Fellowship'],
                ['4711','A7-xi','Interest Received'],
            ]],
        ];

        $expense = [
            ['5100','B1','Department Expenses', [
                ['5101','B1-i','Funeral Dept'],
                ['5102','B1-ii','Transport (Bus)'],
                ['5103','B1-iii','Wednesday Prayer'],
                ['5104','B1-iv','Welfare'],
                ['5105','B1-v','Women Ministry'],
                ['5106','B1-vi','Scholarship & Needy'],
            ]],
            ['5200','B2','Administration Expenses', [
                ['5201','B2-i','General Expense'],
                ['5202','B2-ii',"Rt. Pastor's Pension Payments"],
                ['5203','B2-iii','Salaries & Staff Allowance'],
                ['5204','B2-iv','SSNIT/2nd Tier/PAYE'],
                ['5205','B2-v','Travel & Transport'],
            ]],
            ['5300','B3','Other Expenses', [
                ['5301','B3-i','Cleaning & Sanitation'],
                ['5302','B3-ii','Gen. Coun/Tithe on Tithe'],
                ['5303','B3-iii','Donation (Expense)'],
                ['5304','B3-iv','Internet & Comm Cost'],
                ['5305','B3-v','Medicals'],
                ['5306','B3-vi','Refreshments'],
                ['5307','B3-vii','Repairs & Maintenance'],
                ['5308','B3-viii','Retreat/Revival/Seminar'],
                ['5309','B3-ix','Printing & Stationery'],
                ['5310','B3-x','Utility Bills'],
                ['5311','B3-xi','School Fees'],
                ['5312','B3-xii','Security & Police on Duty'],
                ['5313','B3-xiii','Satellite Church'],
            ]],
        ];

        $sort = 0;

        foreach (['Income' => $income, 'Expense' => $expense] as $type => $groups) {
            foreach ($groups as [$code, $ref, $name, $children]) {
                $group = Account::updateOrCreate(
                    ['code' => $code],
                    [
                        'ref'       => $ref,
                        'name'      => $name,
                        'type'      => $type,
                        'parent_id' => null,
                        'is_group'  => true,
                        'is_active' => true,
                        'sort_order'=> $sort++,
                    ]
                );

                foreach ($children as [$ccode, $cref, $cname]) {
                    Account::updateOrCreate(
                        ['code' => $ccode],
                        [
                            'ref'       => $cref,
                            'name'      => $cname,
                            'type'      => $type,
                            'parent_id' => $group->id,
                            'is_group'  => false,
                            'is_active' => true,
                            'sort_order'=> $sort++,
                        ]
                    );
                }
            }
        }
    }
}