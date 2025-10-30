export default function ApplicationLogo(props) {
    return (
        <img
            {...props}
            src="/assets/img/FinfraTechLogo.jpg"
            alt="FinfraTech Logo"
            className={`h-20 w-20 object-contain rounded-lg ${props.className ?? ''}`}
        />
    );
}
