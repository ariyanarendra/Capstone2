import logoStmik from '../assets/stmik/logo-stmik-kampus-merdeka.png'

export default function Brand({ light = false }) {
  return (
    <div className={`brand ${light ? 'brand--light' : ''}`}>
      <img src={logoStmik} alt="Logo resmi STMIK Bandung dan Kampus Merdeka" />
      <div className="brand__text">
        <strong>GateKampus</strong>
        <span>Portal Perwalian Mahasiswa</span>
      </div>
    </div>
  )
}
