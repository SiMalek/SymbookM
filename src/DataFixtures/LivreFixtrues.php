<?php

namespace App\DataFixtures;

use App\Entity\Livre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use App\Entity\Categories;

class LivreFixtrues extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        for ($j = 1; $j <= 5; $j++) {
            $category = new Categories();
            $names = ['Roman', 'Science Fiction', 'Histoire', 'Biographie', 'Policier'];
            $category->setLibeller($names[$j - 1]);
            $category->setSlug(strtolower(str_replace(' ', '-', $names[$j - 1])));
            $category->setDescription($faker->text);
            $manager->persist($category);
            for ($i = 1; $i <= random_int(10, 15); $i++) {
                $Livre = new Livre();
                $Livre->setTitre($faker->name());
                $Livre->setIsbn($faker->isbn13());
                $Livre->setSlug(strtolower(str_replace(' ', '-', $Livre->getTitre())));
                $Livre->setResume($faker->text);
                $Livre->setEditeur($faker->company());
                $Livre->setPrix($faker->randomFloat(2, 10, 700));
                $Livre->setDateEdition($faker->dateTimeBetween('-5 years', 'now'));
                $Livre->setImage('https://picsum.photos/images/300/?id=' . $i);
                $Livre->setCategorie($category);
                $Livre->setStock($faker->numberBetween(0, 100));

                $manager->persist($Livre);
            }
        }
        $manager->flush();
    }
}
