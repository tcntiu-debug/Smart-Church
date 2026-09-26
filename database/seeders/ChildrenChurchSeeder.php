<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChildrenChurchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data from the provided list - campus_id = 1 (Ikorodu)
        // Each entry: [Parent/Guardian Name, Child's Name, Date Of Birth, Parent's/Guardian PH.Number]
        $records = [
            ['Emmanuel Charles', 'Jeremiah Charles', '', '08116635361, 0906319703'],
            ['', 'Divine Charles', 'April, 3 2020', '7088319273'],
            ['Opemipo Samuels', 'Darasimi', 'february 18,', '8054778149'],
            ['', 'Tobiloba Samuels', 'April, 6', '8054778149'],
            ['', 'Tetisimi', 'Dec-10', '8054778149'],
            ['Onoshoze Bukola', 'Onoshoze kevodyah', 'September, 3', '8065317324'],
            ['Olarore Kayode', 'Olaore Praise', 'Jan-16', '8072649898'],
            ['Ayodele Bukola', 'Nathaniel,', 'Jan-27', '7063816441'],
            ['', 'Jomiloju', 'Msrch 7', '7063816441'],
            ['Alabi Helen', 'Alabi Irewamiri', 'Novermber 7,', '7017597695'],
            ['', 'Alabi Irewamide', 'Oct-25', '7017597695'],
            ['Goodspeed Emmanuel', 'Goodspeed Ella', 'September, 3', '7039034910'],
            ['', 'Goodspeed Praise', 'Novermber 1,', '8063904164'],
            ['', 'Goodspeed Divine', 'Dec-12', '8063904164'],
            ['Adefidipe Adewunmi Oreofe', 'Oluwasegun Zayelle', 'Apr-19', '8053013051'],
            ['Joy Adeshina', 'Annabelle Adeshina', 'Jul-25', '8168461311'],
            ['', 'Tdel Adeshina', 'february 2,', '8168461311'],
            ['', 'Creflo Adeshina', 'Mar-03', '8168461311'],
            ['', 'Daisy Adeshina', 'Mar-03', '8168461311'],
            ['Akele Mary', 'Audi Amari', 'Feb-28', '8167145657'],
            ['Saviour Ufuoma', 'Oghenmaro Saviuor', 'Jul-04', '8168965617, 07069729165'],
            ['Abolade Olajuwon', 'Abolade Henry', 'Apr-16', '9091389958'],
            ['', 'Abolade Aseoluwa', 'Nov-02', '9091389958'],
            ['Helen Richard', 'Shalom', 'Jan-25', '8034106060'],
            ['', 'Angel Tresure', 'Jan-04', '8034106060'],
            ['Temilade Adediyi', 'Dabira David', 'Apr-03', '8175884731'],
            ['Joseph Momoh Halimot', 'Joseph David', 'Jun-06', '8127032806'],
            ['Stella Joseph', 'Jasmine Joseph', 'Nov-25', '8169718942'],
            ['Ojietohaman Nicholas', 'Natha Ojietohaman', 'Apr-04', '8023700440'],
            ['', 'Nicole Ojietohaman', 'Oct-29', '8023700440'],
            ['Olumide Salawu', 'Iretomiwa Salawu', 'Dec-24', '8024229819'],
            ['', 'Morire-Oluwa Salawu', 'May-18', '8024229819'],
            ['Rosy Jerry', 'Ngozi', 'Dec-15', '9022199556'],
            ['', 'Awele', 'Jul-24', '9022199556'],
            ['Vida Ademola', 'Adesewa Ademola', 'Jun-24', '8032012624'],
            ['Mrs. Sotomiwa Lilian', 'Iremide Sotomiwa', 'Apr-16', '9027969398'],
            ['Mrs. Comfort Benjamin', 'David Benjamin', 'Dec-07', '8086888337'],
            ['Mrs. Afamalon Olamiji', 'Joseph Afamalon', 'Apr-29', '9068718574'],
            ['', 'Grace Afamalon', 'Aug-24', '9068718574'],
            ['Mrs. Fasuyi Taiwo', 'Fasuyi MorireOluwa', 'Dec-09', '9044181409'],
            ['Mrs. Adams Blessing', 'Adams Isreal', 'Feb-16', '8135968711'],
            ['', 'Adams Ethan', 'Jun-25', '8135968711'],
            ['Mrs. Fakolujo Oluwakemi', 'Mikayla Fakolujo', 'Jun-08', '8081612089'],
            ['', 'Gabriella Fakolujo', 'September, 15', '8081612089'],
            ['', 'Tehillah Fakolujo', 'Feb-20', '8081612089'],
            ['Mrs. Chidolie Ifeoma', 'Ariella Chidolie', 'Sep-09', '8066065658'],
            ['Mrs. Theresa Babatunde', 'Tomiwa Babatunde', 'Dec-25', '8109502617'],
            ['', 'Tunmise Babatunde', 'Dec-12', '8109502617'],
            ['Mr. Owutan Straford', 'Ella Owutan', 'Dec-25', '9019696519'],
            ['', 'Ema Owutan', 'Dec-12', '9019696519'],
            ['', 'Elena Owutan', 'Dec-12', '9019696519'],
            ['Onayinka Abimbola', 'Joseph', 'Oct-12', '7038445415'],
            ['', 'Rachel', 'Dec-15', '7038445415'],
            ['', 'Daniel', 'Jun-23', '7038445415'],
            ['Oge Ann Omale', 'Vare Omole', 'Apr-22', '8135853998'],
            ['Mrs.Grillo Rebecca', 'George Grillo', 'Jul-05', '7031999758'],
            ['Mrs. Okeke', 'Gabriel', 'Nov-17', '8081362224'],
            ['', 'Lizzy', 'Oct-22', '8081362224'],
            ['Wole Oyefisayo', 'David', 'Nov-17', '8149055921'],
            ['', 'Daaron', 'May-04', '8149055921'],
            ['Demilola Parixonsan', 'Arnette', 'Jun-12', '8187795961'],
            ['', 'Gabrielle', 'Jun-12', '8187795961'],
            ['Mrs. Agwu Adama', 'Melfechi', 'Jul-22', '7039410048'],
            ['', 'Zima', 'Apr-09', '7039410048'],
            ['Adepegie Aninie', 'Adealafie Anmie', 'Oct-25', '9014410695'],
            ['Olabooye Niyi', 'Jason Olabooye', 'Nov-09', '7034988482'],
            ['', 'Jemimalu Olabooye', 'Dec-26', '7034988482'],
            ['Ogunniyi Olalekan', 'Taraoluwa', 'Nov-02', '8028776584'],
            ['', 'Tomisi', 'Mar-14', '8028776584'],
            ['Oluwatoyin Ogundipe', 'Bukunmi Akinpelu', 'May-20', '9158478071'],
            ['', 'Tomiwa Akinpelu', 'Nov-05', '9158478071'],
            ['Miracle Aiyidu', 'Marcel Aiyidu', 'Jun-22', '7037804107'],
            ['Busola Solarin', 'Fope & Fiyin Adeyemi (Twins)', 'May-04', '7036809767'],
            ['Faith Charles', 'Jeremiah Charles', 'Mar-23', '9063199703'],
            ['', 'Divine Charles', 'Apr-03', '9063199703'],
            ['Chineoye Orakwe', 'Praise Obiora', 'Dec-18', '7036141818'],
            ['Mrs. Adenike', 'Damian Tire', 'Feb-15', '9118259813'],
            ['Mrs. Olasunkanmi', 'Akorede', 'Aug-15', '7032810165'],
            ['', 'Oreofeoluwa', 'Jan-07', '7032810165'],
            ['', 'Kikiopeoluwa', 'Jan-07', '7032810165'],
            ['Mr. Oyedeji Olaniyi', 'Morayo Oyedeji', 'Jul-26', '8060720215'],
            ['', 'Morire Oyedeji', 'Apr-13', '8060720215'],
            ['Idowu Olowosebola', 'Desire Olowosebola', 'Sep-20', '8080222717'],
            ['', 'Simon Olowosebola', 'Apr-29', '8080222717'],
            ['Frank Peter', 'Tiaraoluwa Peter', 'Nov-29', '9036157464'],
            ['Olawale', 'Olatishe', 'Jul-17', '8165321519'],
            ['Mrs. Adenike', 'Damian Tire', '', '9118259813'],
            ['Miriam Bello', 'Joshua Bello', 'Nov-12', '8062480897'],
            ['Omale Ogechi', 'Vera Omale', 'Apr-22', '8135853998'],
            ['Amlouh Maureen', 'Philemon Amlouh', 'Feb-12', '8060688856'],
            ['Abiodun Soetan', 'Soetan Olarewanju', 'Apr-23', '8058804736'],
            ['Okonkwo Jessica', 'Okonkwo Jayson', 'Feb-22', '8062193227'],
            ['', 'Okonkwo Jazmyne Bella', 'Jun-13', '8062193227'],
            ['Ovie temotope', 'Ovie Mena', 'Jul-31', '7082387799'],
            ['Mrs. Adanna Agwu', 'Ofufechi Agwu', 'Jul-22', '90113767342'],
            ['', 'Ziona Agwu', 'Apr-09', '90113767342'],
            ['Amaka Chukwudum', '', '', '8038911933'],
            ['Olanrewaju Olowo', 'faith olowo', 'Jun-06', '0703215223, 07064331614'],
            ['Aderonke Ajibola', 'Tobiloba', 'Mar-24', '7083365901'],
            ['', 'Tolulope', 'Aug-22', '7083365901'],
            ['Oshogbuyi Adewale', 'Oshogbuyi Ifeoluwa', 'Jun-15', '8029792001'],
            ['Ifeoluwa Bamisaye', 'Ireayomide Ayo-Bamisaye', 'Decemer 26', '8136204641'],
            ['', 'Fayofunmi Ayo-Bamisaye', 'Aug-31', '8136204641'],
            ['Esther Udeh I.', 'Isichei Canssa Munachi', 'Oct-24', '8142377236'],
            ['', 'Isichei Ciana Keilichi', 'Jul-18', '8142377236'],
            ['Mrs Chineye', 'Obiora Praise', 'Decemer 18', '7036141518'],
            ['Mrs. Soetan', 'Soetan Olarenwaju', 'April, 23', '8058804736'],
            ['Mrs Adegor Virtue', 'Adegor Rukweve', 'Aug-29', '8030905593'],
            ['Grace Kolawole', 'Praise kolawole', 'September, 12', '8163911710'],
            ['Precious Akele', 'Wealth Strength Akele', 'Jul-24', '7048803269'],
            ['Micah Essien', 'Ovie O. Ojo', 'Jul-05', '7019221154'],
            ['Esther Adeniyi', 'Evelyn Adeniyi', 'May-28', '8061360551'],
            ['Peter Attah', 'Aniche Ezewu', 'March 24,', '8024098989'],
            ['', 'Janet Attah', 'Nov-21', '8024098989'],
            ['', 'Joshua Attah', 'Dec-17', '8024098989'],
            ['Olubiyi Olabisi', 'Olubiyi Damola', 'January 6th', '7034352981'],
            ['Omobola Olawole', 'Nifemi Daniel', 'May 11', '8020902516'],
            ['Mrs Grace Ene', 'Jasmine Orawgah', 'May, 7', '8169642984'],
            ['Mrs Ligbago', 'Derrick Ligbago', 'April, 20th', '7037736377'],
        ];

        $insertData = [];
        foreach ($records as $record) {
            $parentName = trim($record[0]);
            $childName = trim($record[1]);
            $dobRaw = trim($record[2]);
            $parentPhone = trim($record[3]);

            // Skip entries with no child name (like Amaka Chukwudum with just a phone)
            if (empty($childName)) {
                continue;
            }

            // Parse and standardize dates to Y-m-d format (matching the date picker submission)
            $dob = self::parseDate($dobRaw);

            $insertData[] = [
                'parent_name'   => $parentName,
                'child_name'    => $childName,
                'dob'           => $dob,
                'parent_phone'  => $parentPhone,
                'campus_id'     => 1, // All records are campus_id 1 (Ikorodu)
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        // Insert in chunks to avoid memory issues
        $chunks = array_chunk($insertData, 50);
        foreach ($chunks as $chunk) {
            DB::table('children_church')->insert($chunk);
        }

        $this->command->info('Seeded ' . count($insertData) . ' children records for Children Church.');
    }

    /**
     * Parse various date formats into standardized Y-m-d format.
     * Handles formats like:
     *   - "April, 3 2020", "April, 6", "December 26"
     *   - "Jan-15", "Dec-10", "Mar-23"
     *   - "January 6th", "April, 20th"
     *   - "2020-04-03" (already standard)
     *
     * @param string $dateStr
     * @return string|null
     */
    private static function parseDate($dateStr)
    {
        if (empty($dateStr)) {
            return null;
        }

        // Clean up - remove trailing commas, "th", "st", "nd", "rd"
        $cleaned = preg_replace('/[,\s]*(th|st|nd|rd)$/i', '', trim($dateStr));

        // Replace comma-space or space after month with space for parsing
        // e.g. "April, 3 2020" -> "April 3 2020"
        $cleaned = preg_replace('/,\s*/', ' ', $cleaned);

        // Handle formats like "Jan-15" -> "Jan 15"
        $cleaned = str_replace('-', ' ', $cleaned);

        // Common month misspellings from the data
        $replacements = [
            '/\bNovermber\b/i' => 'November',
            '/\bDecemer\b/i'   => 'December',
            '/\bMsrch\b/i'     => 'March',
        ];
        $cleaned = preg_replace(array_keys($replacements), array_values($replacements), $cleaned);

        // Trim extra spaces
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        if (empty($cleaned)) {
            return null;
        }

        // Try different formats - order matters! Day-inclusive formats before ambiguous ones.
        // Month-name formats with 4-digit year
        if (preg_match('/\b\d{4}\b/', $cleaned)) {
            $formatsWithYear = [
                'F j Y',   // April 3 2020
                'j F Y',   // 3 April 2020
                'd F Y',   // 03 April 2020
                'M j Y',   // Apr 3 2020
                'j M Y',   // 3 Apr 2020
                'Y-m-d',   // 2020-04-03
                'Y_F_j',   // April_3_2020 (from spaces replaced with _)
            ];
            foreach ($formatsWithYear as $format) {
                $date = \DateTime::createFromFormat($format, $cleaned);
                if ($date !== false) {
                    $errors = \DateTime::getLastErrors();
                    if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                        continue;
                    }
                    return $date->format('Y-m-d');
                }
            }
        }

        // Month-name formats without year (assume current year)
        $formatsNoYear = [
            'F j',     // April 3
            'M j',     // Apr 3
            'j F',     // 3 April
            'j M',     // 3 Apr
        ];
        foreach ($formatsNoYear as $format) {
            $date = \DateTime::createFromFormat($format, $cleaned);
            if ($date !== false) {
                $errors = \DateTime::getLastErrors();
                if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    continue;
                }
                return $date->format('Y-m-d');
            }
        }

        // If all parsing fails, try strtotime
        $ts = strtotime($cleaned);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        // If nothing works, store as null rather than garbled text
        return null;
    }
}
