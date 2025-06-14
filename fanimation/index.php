<?php
require 'includes/db_connect.php';
include 'includes/header.php';

?>
<style>
    .introduction {
        color: #444245
    }
    .plx-image1 {
    background-image: url(images/banners/kwartet_video_hero.jpg);
    text-align: center;
    width: 100%;
    height: 600px;
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

.plx-image1 video {
    width: 600px;
    float: right;
    padding: 50px;
    margin-right: 50px;
}

.plx-image2 {
    background-image: url(images/banners/air-apparent-banner-bkgr-2.jpg);
    text-align: center;
    width: 100%;
    height: 600px;
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    display: flex;
    align-items: center;
}


.content-2 {
    display: flex;
    justify-content: center;
    padding: 80px;
}

.content-2 img {
    width: 45vw;
    padding: 0 20px;
}

.box-content {
    width: 500px;
    background-color: rgba(146, 36, 146, 0.5);
    padding: 50px;
    margin-left: 100px;
    font-family: Arial, Helvetica, sans-serif;
}
</style>


<div id="carouselExampleFade" class="carousel slide carousel-fade" data-bs-ride="carousel">
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="images/banners/animation1.jpg" class="d-block w-100" alt="Image 1">
            <div class="carousel-content">
                <h1>Pleated Perfection</h1>
                <p>NEW 52" sweep pleated blades + 12" light kit for TriAire™</p>
                <a href="#" class="btn">Learn More</a>
            </div>
        </div>
        <div class="carousel-item">
            <img src="images/banners/animation2.jpg" class="d-block w-100" alt="Image 2">
            <div class="carousel-content">
                <h1>Pleated Perfection</h1>
                <p>NEW 52" sweep pleated blades + 12" light kit for TriAire™</p>
                <a href="#" class="btn">Learn More</a>
            </div>
        </div>
        <div class="carousel-item">
            <img src="images/banners/animation3.jpg" class="d-block w-100" alt="Image 3">
            <div class="carousel-content">
                <h1>Pleated Perfection</h1>
                <p>NEW 52" sweep pleated blades + 12" light kit for TriAire™</p>
                <a href="#" class="btn ">Learn More</a>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>
<div class="content-1 mx-auto mt-4 mb-4 w-50 fs-5 fw-bold text-muted">
    <p>Fanimation fans are the perfect fusion of beauty and functionality. With designs for every style and
        technology-driven controls for your convenience, Fanimation fans inspire your home. They integrate into any
        space and allow you to make a statement that is all your own.</p>

</div>
<div class="plx-image1">
    <video src="images/banners/March25_CCT_Select_v06.mp4" autoplay loop muted></video>
</div>
<div class="content-2">
    <a href=""><img src="images/banners/banner-fanimation-studio1_hover.jpg" alt=""></a>
    <a href=""><img src="images/banners/showroomcollection2018_hover.jpg" alt=""></a>
</div>
<div class="plx-image2">
    <div class="box-content text-white fw-bold text-start">
        <h4>ABOUT US</h4>
        <h2>Air Apparent</h2>
        <p>From the very first fan we created more than 30 years ago to the newest ones in our portfolio, we create fans you can’t wait to show off! The same ingenuity and quality craftsmanship that gave birth to Fanimation continues to guide us today.</p>
    </div>
</div>

</div>

<?php
include 'includes/footer.php'
?>
